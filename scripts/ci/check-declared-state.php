#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Garde-fou « état déclaré » — voir docs/project/declared-state-guardrail.md
 *
 * Checks that what the repository DECLARES about itself still matches reality:
 *   1. docs/project/open-issues.yml vs the real open issues on GitHub (both ways);
 *   2. the entity list declared in AI_CONTEXT.md vs apps/backend/src/Entity/;
 *   3. the order statuses declared in AI_CONTEXT.md vs App\Enum\OrderStatus.
 *
 * Zero dependency on purpose: plain PHP 8.3+, no composer install, no ext-yaml.
 * The YAML reader below handles only the narrow subset open-issues.yml uses.
 *
 * Exit codes:
 *   0 — no divergence (warnings may have been printed)
 *   1 — real divergence, the declared state must be fixed
 *   2 — the script itself could not run (bad arguments, unreadable file)
 */

const EXIT_OK = 0;
const EXIT_DIVERGENCE = 1;
const EXIT_SCRIPT_ERROR = 2;

/** Failures: block the CI. */
$problems = [];
/** Warnings: printed, never blocking (cannot-verify situations, soft drift). */
$warnings = [];

// ---------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------

$root = \dirname(__DIR__, 2);
$options = [
    'repo' => getenv('GITHUB_REPOSITORY') ?: 'pivox/click-and-collect-superette',
    'issues-file' => $root.'/docs/project/open-issues.yml',
    'context-file' => $root.'/AI_CONTEXT.md',
    'entity-dir' => $root.'/apps/backend/src/Entity',
    'enum-file' => $root.'/apps/backend/src/Enum/OrderStatus.php',
];
$skipIssues = false;
$repoOverridden = false;

foreach (\array_slice($argv, 1) as $arg) {
    if ('--skip-issues' === $arg) {
        $skipIssues = true;

        continue;
    }
    if ('--help' === $arg || '-h' === $arg) {
        echo "Usage: php scripts/ci/check-declared-state.php [--skip-issues] [--repo=owner/name]\n";
        echo "       [--issues-file=PATH] [--context-file=PATH] [--entity-dir=PATH] [--enum-file=PATH]\n";
        exit(EXIT_OK);
    }
    if (!\preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m) || !\array_key_exists($m[1], $options)) {
        \fwrite(\STDERR, \sprintf("Argument inconnu : %s (--help pour l'usage)\n", $arg));
        exit(EXIT_SCRIPT_ERROR);
    }
    $options[$m[1]] = $m[2];
    if ('repo' === $m[1]) {
        $repoOverridden = true;
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function readFileOrFail(string $path, string $label): string
{
    if (!\is_file($path) || !\is_readable($path)) {
        \fwrite(\STDERR, \sprintf("Fichier %s introuvable ou illisible : %s\n", $label, $path));
        exit(EXIT_SCRIPT_ERROR);
    }

    return (string) \file_get_contents($path);
}

/**
 * Minimal reader for the open-issues.yml subset:
 *   repository: owner/name
 *   issues:
 *     - number: 123
 *       title: "..."
 *
 * Anything outside this subset is reported with its line number rather than
 * silently mis-parsed — a guardrail that quietly reads nothing is worse than
 * no guardrail at all.
 *
 * @return array{repository: ?string, issues: list<int>, errors: list<string>}
 */
function parseIssuesYaml(string $raw): array
{
    $repository = null;
    $issues = [];
    $errors = [];
    $inIssues = false;

    foreach (\explode("\n", $raw) as $index => $line) {
        $lineNo = $index + 1;
        $line = \rtrim($line, "\r");

        // Strip full-line comments and blank lines.
        if ('' === \trim($line) || \preg_match('/^\s*#/', $line)) {
            continue;
        }

        // Top-level `key: value`
        if (\preg_match('/^([a-z_]+):\s*(.*)$/', $line, $m)) {
            $key = $m[1];
            $value = \trim($m[2]);
            $inIssues = false;

            if ('repository' === $key) {
                $repository = \trim($value, "\"' ");

                continue;
            }
            if ('issues' === $key) {
                $inIssues = true;
                if ('' !== $value) {
                    $errors[] = \sprintf('ligne %d : `issues:` doit être suivi d\'une liste indentée, pas d\'une valeur inline.', $lineNo);
                }

                continue;
            }

            $errors[] = \sprintf('ligne %d : clé de premier niveau inattendue `%s` (attendu `repository` ou `issues`).', $lineNo, $key);

            continue;
        }

        // List item start: `  - number: 123`
        if (\preg_match('/^\s+-\s+([a-z_]+):\s*(.*)$/', $line, $m)) {
            if (!$inIssues) {
                $errors[] = \sprintf('ligne %d : élément de liste en dehors de `issues:`.', $lineNo);

                continue;
            }
            if ('number' !== $m[1]) {
                $errors[] = \sprintf('ligne %d : chaque élément doit commencer par `number:` (trouvé `%s:`).', $lineNo, $m[1]);

                continue;
            }
            $value = \trim($m[2]);
            if (!\preg_match('/^\d+$/', $value)) {
                $errors[] = \sprintf('ligne %d : `number:` doit être un entier (trouvé « %s »).', $lineNo, $value);

                continue;
            }
            $issues[] = (int) $value;

            continue;
        }

        // Continuation key inside a list item: `    title: "..."`
        if (\preg_match('/^\s+([a-z_]+):\s*(.*)$/', $line, $m)) {
            if (!$inIssues) {
                $errors[] = \sprintf('ligne %d : clé indentée en dehors de `issues:`.', $lineNo);
            } elseif (!\in_array($m[1], ['title', 'note'], true)) {
                $errors[] = \sprintf('ligne %d : clé `%s` non supportée dans un élément (attendu `title` ou `note`).', $lineNo, $m[1]);
            }

            continue;
        }

        $errors[] = \sprintf('ligne %d : syntaxe non supportée par le sous-ensemble YAML accepté : « %s ».', $lineNo, \trim($line));
    }

    return ['repository' => $repository, 'issues' => $issues, 'errors' => $errors];
}

/**
 * Extracts a `## Heading` section body from a Markdown document.
 */
function markdownSection(string $markdown, string $heading): ?string
{
    $pattern = '/^##\s+'.\preg_quote($heading, '/').'\s*$(.*?)(?=^##\s|\z)/ms';
    if (1 !== \preg_match($pattern, $markdown, $m)) {
        return null;
    }

    return $m[1];
}

/**
 * @return array{status: string, body?: string, message?: string}
 */
function httpGetJson(string $url, ?string $token): array
{
    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: declared-state-guardrail',
    ];
    if (null !== $token && '' !== $token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    if (\function_exists('curl_init')) {
        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HTTPHEADER => $headers,
            \CURLOPT_HEADER => true,
            \CURLOPT_TIMEOUT => 30,
            \CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = \curl_exec($ch);
        if (false === $response) {
            $message = \curl_error($ch);
            \curl_close($ch);

            return ['status' => 'unavailable', 'message' => $message];
        }
        $code = (int) \curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);
        $headerSize = (int) \curl_getinfo($ch, \CURLINFO_HEADER_SIZE);
        \curl_close($ch);
        $body = \substr((string) $response, $headerSize);

        if (200 !== $code) {
            return ['status' => 'unavailable', 'message' => \sprintf('HTTP %d — %s', $code, \substr(\trim($body), 0, 300))];
        }

        return ['status' => 'ok', 'body' => $body];
    }

    $context = \stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => \implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ]);
    $body = @\file_get_contents($url, false, $context);
    if (false === $body) {
        return ['status' => 'unavailable', 'message' => 'échec de la requête HTTP (allow_url_fopen ?)'];
    }
    $code = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (\preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
            $code = (int) $m[1];
        }
    }
    if (200 !== $code) {
        return ['status' => 'unavailable', 'message' => \sprintf('HTTP %d — %s', $code, \substr(\trim($body), 0, 300))];
    }

    return ['status' => 'ok', 'body' => $body];
}

/**
 * Fetches every open issue of a repository, pull requests excluded.
 *
 * GitHub's /issues endpoint returns pull requests too — they carry a
 * `pull_request` key. Not filtering them would produce massive false
 * positives ("hundreds of undeclared open issues").
 *
 * @return array{status: string, issues?: array<int, string>, message?: string}
 */
function fetchOpenIssues(string $repo, ?string $token): array
{
    $issues = [];
    $maxPages = 50; // 5000 issues — safety stop, never reached in practice

    for ($page = 1; $page <= $maxPages; ++$page) {
        $url = \sprintf(
            'https://api.github.com/repos/%s/issues?state=open&per_page=100&page=%d',
            $repo,
            $page,
        );
        $result = httpGetJson($url, $token);
        if ('ok' !== $result['status']) {
            return ['status' => 'unavailable', 'message' => $result['message'] ?? 'inconnu'];
        }

        $decoded = \json_decode($result['body'] ?? '', true);
        if (!\is_array($decoded)) {
            return ['status' => 'unavailable', 'message' => 'réponse JSON illisible depuis l\'API GitHub'];
        }
        if ([] === $decoded) {
            break;
        }

        foreach ($decoded as $item) {
            if (!\is_array($item) || !isset($item['number'])) {
                continue;
            }
            if (isset($item['pull_request'])) {
                continue; // a pull request, not an issue
            }
            $issues[(int) $item['number']] = (string) ($item['title'] ?? '');
        }

        if (\count($decoded) < 100) {
            break;
        }
    }

    \ksort($issues);

    return ['status' => 'ok', 'issues' => $issues];
}

// ---------------------------------------------------------------------------
// Check 1 — issues déclarées ouvertes vs issues réellement ouvertes
// ---------------------------------------------------------------------------

echo "== Garde-fou état déclaré ==\n\n";

$issuesRaw = readFileOrFail($options['issues-file'], 'open-issues.yml');
$parsed = parseIssuesYaml($issuesRaw);

foreach ($parsed['errors'] as $error) {
    $problems[] = \sprintf(
        "open-issues.yml — %s\n    → corriger la syntaxe du fichier (format attendu documenté dans docs/project/declared-state-guardrail.md).",
        $error,
    );
}

$declared = \array_values(\array_unique($parsed['issues']));
\sort($declared);
$duplicates = \array_keys(\array_filter(\array_count_values($parsed['issues']), static fn (int $n) => $n > 1));
foreach ($duplicates as $dup) {
    $warnings[] = \sprintf('open-issues.yml déclare l\'issue #%d plusieurs fois — supprimer les doublons.', $dup);
}

// Precedence: explicit --repo > `repository:` in open-issues.yml > GITHUB_REPOSITORY.
$repo = $repoOverridden ? $options['repo'] : ($parsed['repository'] ?? $options['repo']);
echo \sprintf("[1/3] Issues — dépôt %s, %d issue(s) déclarée(s) ouverte(s).\n", $repo, \count($declared));

if ($skipIssues) {
    $warnings[] = 'Vérification des issues ignorée (--skip-issues) — la divergence issues n\'a PAS été contrôlée.';
    echo "      ignoré (--skip-issues).\n";
} else {
    $token = getenv('GITHUB_TOKEN') ?: (getenv('GH_TOKEN') ?: null);
    if (null === $token || '' === $token) {
        $warnings[] = "Aucun GITHUB_TOKEN/GH_TOKEN dans l'environnement : l'état réel des issues n'a pas pu être vérifié.\n"
            ."    → en CI, le workflow fournit automatiquement `secrets.GITHUB_TOKEN` ; sur un fork sans token, c'est attendu.\n"
            .'    → en local : GITHUB_TOKEN=$(gh auth token) php scripts/ci/check-declared-state.php';
        echo "      non vérifié : pas de token (avertissement, non bloquant).\n";
    } else {
        $fetched = fetchOpenIssues($repo, $token);
        if ('ok' !== $fetched['status']) {
            $warnings[] = \sprintf(
                "API GitHub injoignable (%s) : l'état réel des issues n'a pas pu être vérifié.\n"
                .'    → panne, rate limit ou token sans droit `issues: read`. Ce n\'est PAS une divergence : la CI ne doit pas échouer là-dessus.',
                $fetched['message'] ?? 'inconnu',
            );
            echo "      non vérifié : API injoignable (avertissement, non bloquant).\n";
        } else {
            $realIssues = $fetched['issues'] ?? [];
            $real = \array_keys($realIssues);
            echo \sprintf("      %d issue(s) réellement ouverte(s) sur GitHub (pull requests exclues).\n", \count($real));

            $closedButDeclared = \array_values(\array_diff($declared, $real));
            $openButUndeclared = \array_values(\array_diff($real, $declared));

            foreach ($closedButDeclared as $number) {
                $problems[] = \sprintf(
                    "Issue #%d est déclarée ouverte dans docs/project/open-issues.yml mais n'est PAS ouverte sur %s"
                    ." (fermée, transférée ou supprimée).\n"
                    ."    → retirer l'entrée `- number: %d` de docs/project/open-issues.yml.\n"
                    .'    → vérifier aussi qu\'AI_CONTEXT.md ne la présente plus comme un chantier en cours.',
                    $number,
                    $repo,
                    $number,
                );
            }
            foreach ($openButUndeclared as $number) {
                $title = $realIssues[$number] ?? '';
                $problems[] = \sprintf(
                    "Issue #%d « %s » est ouverte sur %s mais absente de docs/project/open-issues.yml.\n"
                    ."    → ajouter dans docs/project/open-issues.yml :\n"
                    ."          - number: %d\n"
                    ."            title: \"%s\"\n"
                    .'    → et vérifier si ce chantier doit apparaître dans AI_CONTEXT.md (c\'est cet oubli qui a fait rater l\'épic CATALOG-AI).',
                    $number,
                    $title,
                    $repo,
                    $number,
                    \str_replace('"', "'", $title),
                );
            }

            if ([] === $closedButDeclared && [] === $openButUndeclared) {
                echo "      OK — les deux listes concordent.\n";
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Check 2 — entités Doctrine déclarées vs fichiers réels
// ---------------------------------------------------------------------------

$context = readFileOrFail($options['context-file'], 'AI_CONTEXT.md');
$contextName = \basename($options['context-file']);

echo "\n[2/3] Entités Doctrine.\n";

$entityDir = $options['entity-dir'];
$realEntities = [];
if (!\is_dir($entityDir)) {
    $warnings[] = \sprintf('Dossier des entités introuvable (%s) — vérification des entités impossible.', $entityDir);
} else {
    foreach (\glob($entityDir.'/*.php') ?: [] as $file) {
        $content = (string) \file_get_contents($file);
        // Matches #[ORM\Entity] and #[ORM\Entity(repositoryClass: ...)]
        if (\preg_match('/#\[\s*ORM\\\\Entity\b/', $content)) {
            $realEntities[] = \basename($file, '.php');
        }
    }
    \sort($realEntities);
}

$entitySection = markdownSection($context, 'Entités métier de référence');
if (null === $entitySection) {
    // AI_CONTEXT.md is a narrative document under concurrent edition: a missing
    // or renamed section is a documentation smell, not a reason to block CI.
    $warnings[] = \sprintf(
        "Section « ## Entités métier de référence » introuvable dans %s — vérification des entités non effectuée.\n"
        .'    → si la section a été renommée, mettre à jour `markdownSection()` dans scripts/ci/check-declared-state.php.',
        $contextName,
    );
    echo "      section introuvable (avertissement, non bloquant).\n";
} elseif ([] === $realEntities) {
    echo "      dossier des entités non analysable (avertissement, non bloquant).\n";
} else {
    // Backticked PascalCase tokens only: this skips property names
    // (`baseNameFr`), paths and snake_case columns that also appear in backticks.
    \preg_match_all('/`([A-Z][A-Za-z0-9]*)`/', $entitySection, $m);
    $declaredEntities = \array_values(\array_unique($m[1]));
    \sort($declaredEntities);

    echo \sprintf(
        "      %d classe(s) citée(s) dans la section, %d fichier(s) portant #[ORM\\Entity].\n",
        \count($declaredEntities),
        \count($realEntities),
    );

    $missing = \array_values(\array_diff($realEntities, $declaredEntities));
    $ghosts = \array_values(\array_diff($declaredEntities, $realEntities));

    if ([] !== $missing) {
        $problems[] = \sprintf(
            "%d entité(s) existent dans %s mais ne sont pas citées dans la section « Entités métier de référence » de %s :\n"
            ."        %s\n"
            .'    → ajouter ces classes dans la section, dans le groupe métier qui leur correspond.',
            \count($missing),
            \str_replace(\dirname(__DIR__, 2).'/', '', $entityDir),
            $contextName,
            \implode(', ', $missing),
        );
    }

    if ([] !== $ghosts) {
        // Soft drift: the section legitimately mentions historical names
        // (e.g. `MerchantProductOffer`, renamed to `MerchantProduct`).
        $warnings[] = \sprintf(
            "Classe(s) citée(s) dans la section entités sans fichier correspondant : %s.\n"
            .'    → normal s\'il s\'agit d\'un ancien nom mentionné pour mémoire ; à corriger sinon.',
            \implode(', ', $ghosts),
        );
    }

    // The section opens with a claimed count ("**61 entités**"): keep it honest.
    if (\preg_match('/\*\*\s*(\d+)\s+entit[ée]s?\s*\*\*/u', $entitySection, $countMatch)) {
        $claimed = (int) $countMatch[1];
        if ($claimed !== \count($realEntities)) {
            $problems[] = \sprintf(
                "%s annonce « **%d entités** » alors que %s contient %d fichier(s) portant #[ORM\\Entity].\n"
                .'    → corriger le nombre annoncé dans la section « Entités métier de référence ».',
                $contextName,
                $claimed,
                \str_replace(\dirname(__DIR__, 2).'/', '', $entityDir),
                \count($realEntities),
            );
        }
    } else {
        $warnings[] = \sprintf(
            'Aucun nombre d\'entités annoncé (format attendu « **61 entités** ») dans la section de %s — comptage non vérifié.',
            $contextName,
        );
    }

    if ([] === $missing && [] === $ghosts) {
        echo "      OK — la liste déclarée couvre toutes les entités.\n";
    }
}

// ---------------------------------------------------------------------------
// Check 3 — statuts de commande déclarés vs App\Enum\OrderStatus
// ---------------------------------------------------------------------------

echo "\n[3/3] Statuts de commande.\n";

$statusSection = markdownSection($context, 'Statuts de commande');
$enumPath = $options['enum-file'];

if (!\is_file($enumPath)) {
    $warnings[] = \sprintf('Enum introuvable (%s) — vérification des statuts non effectuée.', $enumPath);
    echo "      enum introuvable (avertissement, non bloquant).\n";
} elseif (null === $statusSection) {
    $warnings[] = \sprintf(
        "Section « ## Statuts de commande » introuvable dans %s — vérification des statuts non effectuée.\n"
        .'    → si la section a été renommée, mettre à jour `markdownSection()` dans scripts/ci/check-declared-state.php.',
        $contextName,
    );
    echo "      section introuvable (avertissement, non bloquant).\n";
} else {
    \preg_match_all('/^\s*[-*]\s+`([a-z_]+)`/m', $statusSection, $m);
    $declaredStatuses = \array_values(\array_unique($m[1]));
    \sort($declaredStatuses);

    \preg_match_all('/case\s+\w+\s*=\s*\'([a-z_]+)\'\s*;/', (string) \file_get_contents($enumPath), $em);
    $realStatuses = \array_values(\array_unique($em[1]));
    \sort($realStatuses);

    echo \sprintf(
        "      %d statut(s) déclaré(s), %d case(s) dans OrderStatus.\n",
        \count($declaredStatuses),
        \count($realStatuses),
    );

    $missingStatuses = \array_values(\array_diff($realStatuses, $declaredStatuses));
    $extraStatuses = \array_values(\array_diff($declaredStatuses, $realStatuses));

    if ([] !== $missingStatuses) {
        $problems[] = \sprintf(
            "Statut(s) présent(s) dans App\\Enum\\OrderStatus mais absent(s) de la section « Statuts de commande » de %s : %s.\n"
            .'    → ajouter ces statuts à la liste de %s.',
            $contextName,
            \implode(', ', $missingStatuses),
            $contextName,
        );
    }
    if ([] !== $extraStatuses) {
        $problems[] = \sprintf(
            "Statut(s) déclaré(s) dans %s sans `case` correspondant dans App\\Enum\\OrderStatus : %s.\n"
            .'    → retirer ces statuts de %s, ou les ajouter à l\'enum s\'ils devaient exister.',
            $contextName,
            \implode(', ', $extraStatuses),
            $contextName,
        );
    }
    if ([] === $missingStatuses && [] === $extraStatuses) {
        echo "      OK — les statuts concordent.\n";
    }
}

// ---------------------------------------------------------------------------
// Rapport
// ---------------------------------------------------------------------------

echo "\n".\str_repeat('-', 72)."\n";

if ([] !== $warnings) {
    echo \sprintf("\nAVERTISSEMENTS (%d) — non bloquants :\n\n", \count($warnings));
    foreach ($warnings as $i => $warning) {
        echo \sprintf("  %d. %s\n\n", $i + 1, $warning);
    }
}

if ([] === $problems) {
    echo "\nRESULTAT : OK — l'état déclaré dans le dépôt correspond à la réalité.\n";
    exit(EXIT_OK);
}

echo \sprintf("\nDIVERGENCES (%d) — à corriger :\n\n", \count($problems));
foreach ($problems as $i => $problem) {
    echo \sprintf("  %d. %s\n\n", $i + 1, $problem);
}
echo "RESULTAT : ECHEC — l'état déclaré a dérivé de la réalité.\n";
echo "Voir docs/project/declared-state-guardrail.md pour la procédure de correction.\n";

exit(EXIT_DIVERGENCE);
