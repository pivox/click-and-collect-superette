# Bounded Contexts

## Shop Context

Responsabilités :

- supérette
- QR code magasin
- informations publiques
- horaires

### Aggregates

- Shop
- ShopQrCode

---

## Catalog Context

Responsabilités :

- produits
- catégories
- prix
- disponibilité

### Aggregates

- Product
- Category
- ProductAvailability

---

## Kadhia Context

Responsabilités :

- panier
- lignes
- quantités
- total TND

### Aggregates

- Kadhia
- KadhiaLine

---

## Booking Context

Responsabilités :

- rendez-vous
- capacité
- créneaux

### Aggregates

- PickupSlot
- Reservation

---

## Order Context
n
Responsabilités :

- commande
- statuts
- workflow métier
- validation

### Aggregates

- Order
- OrderLine

---

## Pickup Context

Responsabilités :

- QR code retrait
- double validation
- finalisation

### Aggregates

- PickupSession
- PickupQrCode

---

## Identity Context

Responsabilités :

- comptes
- rôles
- authentification

### Aggregates

- User
- Role
