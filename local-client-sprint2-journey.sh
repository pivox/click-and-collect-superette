#!/usr/bin/env bash

set -euo pipefail

API_BASE="${API_BASE:-http://localhost:8000/api}"

CLIENT_EMAIL="${CLIENT_EMAIL:-client.demo@kadhia.local}"
CLIENT_PASSWORD="${CLIENT_PASSWORD:-secret123}"

STORE_QUERY="${STORE_QUERY:-Amen}"
STORE_CITY="${STORE_CITY:-}"
PRODUCT_QUERY="${PRODUCT_QUERY:-lait}"

QTY_1="${QTY_1:-1}"
QTY_2="${QTY_2:-1}"

require_bin() {
	if ! command -v "$1" >/dev/null 2>&1; then
		echo "Erreur: '$1' est requis." >&2
		exit 1
	fi
}

urlencode() {
	jq -rn --arg value "$1" '$value|@uri'
}

json_get() {
	local json="$1"
	local path="$2"

	jq -r "$path // empty" <<< "${json}"
}

require_bin curl
require_bin jq

echo "API_BASE=${API_BASE}"
echo "Connexion client: ${CLIENT_EMAIL}"

LOGIN_RESPONSE="$(
	curl -sS -f \
		-X POST "${API_BASE}/auth/login" \
		-H "Content-Type: application/json" \
		-d "$(jq -n \
			--arg email "${CLIENT_EMAIL}" \
			--arg password "${CLIENT_PASSWORD}" \
			'{email: $email, password: $password}'
		)"
)"
echo "Login response: ${LOGIN_RESPONSE}" >&2

TOKEN="$(json_get "${LOGIN_RESPONSE}" '.token // .jwt // .access_token')"

if [ -z "${TOKEN}" ]; then
	echo "Erreur: token JWT introuvable dans la réponse de login." >&2
	echo "${LOGIN_RESPONSE}" | jq . >&2
	exit 1
fi

echo "✅ Client connecté"

SEARCH_URL="${API_BASE}/stores/search"
QUERY_PARAMS=()

if [ -n "${STORE_QUERY}" ]; then
	QUERY_PARAMS+=("query=$(urlencode "${STORE_QUERY}")")
fi

if [ -n "${STORE_CITY}" ]; then
	QUERY_PARAMS+=("city=$(urlencode "${STORE_CITY}")")
fi

if [ "${#QUERY_PARAMS[@]}" -gt 0 ]; then
	SEARCH_URL="${SEARCH_URL}?$(IFS='&'; echo "${QUERY_PARAMS[*]}")"
fi

echo "Recherche de supérettes: ${SEARCH_URL}"

STORE_RESPONSE="$(
	curl -sS -f \
		-X GET "${SEARCH_URL}" \
		-H "Accept: application/json"
)"

STORE_COUNT="$(jq -r '.total // (.items | length) // 0' <<< "${STORE_RESPONSE}")"

if [ "${STORE_COUNT}" = "0" ] && [ -n "${STORE_QUERY}" ] && [[ "${STORE_QUERY}" == *-* ]]; then
	FALLBACK_STORE_QUERY="${STORE_QUERY//-/ }"
	FALLBACK_SEARCH_URL="${API_BASE}/stores/search?query=$(urlencode "${FALLBACK_STORE_QUERY}")"

	echo "Aucun résultat avec '${STORE_QUERY}', nouvelle tentative avec '${FALLBACK_STORE_QUERY}'"
	echo "Recherche de supérettes: ${FALLBACK_SEARCH_URL}"

	STORE_RESPONSE="$(
		curl -sS -f \
			-X GET "${FALLBACK_SEARCH_URL}" \
			-H "Accept: application/json"
	)"

	STORE_COUNT="$(jq -r '.total // (.items | length) // 0' <<< "${STORE_RESPONSE}")"
fi

if [ "${STORE_COUNT}" = "0" ]; then
	echo "Aucune supérette trouvée."
	echo "${STORE_RESPONSE}" | jq .
	exit 0
fi

STORE_ID="$(jq -r '.items[0].store_id // .items[0].storeId // .items[0].id // empty' <<< "${STORE_RESPONSE}")"
STORE_NAME="$(jq -r '.items[0].name // "store sans nom"' <<< "${STORE_RESPONSE}")"

if [ -z "${STORE_ID}" ]; then
	echo "Erreur: impossible de récupérer store_id." >&2
	echo "${STORE_RESPONSE}" | jq . >&2
	exit 1
fi

echo "✅ Supérette sélectionnée: ${STORE_NAME} (${STORE_ID})"

echo "Connexion / reconnaissance du client au store..."

VISIT_RESPONSE="$(
	curl -sS -f \
		-X POST "${API_BASE}/me/stores/${STORE_ID}/visit" \
		-H "Authorization: Bearer ${TOKEN}" \
		-H "Content-Type: application/json" \
		-H "Accept: application/json" \
		-d '{"source":"search"}'
)"

echo "✅ Store reconnu par le client"
echo "${VISIT_RESPONSE}" | jq .

CATALOG_URL="${API_BASE}/stores/${STORE_ID}/catalog"

if [ -n "${PRODUCT_QUERY}" ]; then
	CATALOG_URL="${CATALOG_URL}?query=$(urlencode "${PRODUCT_QUERY}")"
fi

echo "Recherche de produits: ${CATALOG_URL}"

CATALOG_RESPONSE="$(
	curl -sS -f \
		-X GET "${CATALOG_URL}" \
		-H "Accept: application/json"
)"

PRODUCT_IDS=()
while IFS= read -r product_id; do
	if [ -n "${product_id}" ]; then
		PRODUCT_IDS+=("${product_id}")
	fi
done < <(
	jq -r '
		.items
		| map(select((.is_available // .isAvailable // true) == true))
		| .[0:2]
		| .[]
		| .id
	' <<< "${CATALOG_RESPONSE}"
)

PRODUCT_COUNT="${#PRODUCT_IDS[@]}"

if [ "${PRODUCT_COUNT}" -lt 2 ]; then
	echo "Erreur: moins de 2 produits disponibles trouvés dans ce store." >&2
	echo "Produits disponibles trouvés: ${PRODUCT_COUNT}" >&2
	echo "${CATALOG_RESPONSE}" | jq . >&2
	exit 1
fi

PRODUCT_1_ID="${PRODUCT_IDS[0]}"
PRODUCT_2_ID="${PRODUCT_IDS[1]}"

PRODUCT_1_LABEL="$(
	jq -r --arg id "${PRODUCT_1_ID}" '
		.items[]
		| select(.id == $id)
		| "\(.name_fr // .nameFr // "produit") - \(.brand // "marque inconnue") - \(.price_tnd // .priceTnd // "?") TND"
	' <<< "${CATALOG_RESPONSE}"
)"

PRODUCT_2_LABEL="$(
	jq -r --arg id "${PRODUCT_2_ID}" '
		.items[]
		| select(.id == $id)
		| "\(.name_fr // .nameFr // "produit") - \(.brand // "marque inconnue") - \(.price_tnd // .priceTnd // "?") TND"
	' <<< "${CATALOG_RESPONSE}"
)"

echo "✅ Produit 1: ${PRODUCT_1_LABEL} (${PRODUCT_1_ID})"
echo "✅ Produit 2: ${PRODUCT_2_LABEL} (${PRODUCT_2_ID})"

echo "Création d'une nouvelle Kadhia..."

KADHIA_RESPONSE="$(
	curl -sS -f \
		-X POST "${API_BASE}/me/stores/${STORE_ID}/kadhias" \
		-H "Authorization: Bearer ${TOKEN}" \
		-H "Content-Type: application/json" \
		-H "Accept: application/json" \
		-d '{"notes":"Kadhia créée par script de test"}'
)"

KADHIA_ID="$(jq -r '.id // empty' <<< "${KADHIA_RESPONSE}")"

if [ -z "${KADHIA_ID}" ]; then
	echo "Erreur: impossible de récupérer l'id de la Kadhia." >&2
	echo "${KADHIA_RESPONSE}" | jq . >&2
	exit 1
fi

echo "✅ Kadhia créée: ${KADHIA_ID}"
echo "${KADHIA_RESPONSE}" | jq .

echo "Ajout du produit 1 à la Kadhia..."

KADHIA_AFTER_PRODUCT_1="$(
	curl -sS -f \
		-X PUT "${API_BASE}/me/kadhias/${KADHIA_ID}/lines/${PRODUCT_1_ID}" \
		-H "Authorization: Bearer ${TOKEN}" \
		-H "Content-Type: application/json" \
		-H "Accept: application/json" \
		-d "$(jq -n --argjson quantity "${QTY_1}" '{quantity: $quantity}')"
)"

echo "✅ Produit 1 ajouté"

echo "Ajout du produit 2 à la Kadhia..."

KADHIA_AFTER_PRODUCT_2="$(
	curl -sS -f \
		-X PUT "${API_BASE}/me/kadhias/${KADHIA_ID}/lines/${PRODUCT_2_ID}" \
		-H "Authorization: Bearer ${TOKEN}" \
		-H "Content-Type: application/json" \
		-H "Accept: application/json" \
		-d "$(jq -n --argjson quantity "${QTY_2}" '{quantity: $quantity}')"
)"

echo "✅ Produit 2 ajouté"

echo "Kadhia finale:"
echo "${KADHIA_AFTER_PRODUCT_2}" | jq .

echo ""
echo "Résumé:"
echo "- store_id: ${STORE_ID}"
echo "- kadhia_id: ${KADHIA_ID}"
echo "- product_1_id: ${PRODUCT_1_ID}, quantity: ${QTY_1}"
echo "- product_2_id: ${PRODUCT_2_ID}, quantity: ${QTY_2}"
