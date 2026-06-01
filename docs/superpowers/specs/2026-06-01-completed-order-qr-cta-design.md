# Completed Order QR CTA Design

## Context

Issue #294 reports that a customer order already marked as `completed` still shows the disabled QR withdrawal CTA with the text "QR retrait — disponible quand prête" on `/orders/{orderId}`.

This is inconsistent because the same page already shows the final badge "Récupérée" and the timeline step "Commande récupérée".

## Decision

Use option A: keep the QR CTA area, but render an explicit final state for `completed` orders.

For `ready` and `pickup_pending`, the page keeps the active "Afficher le QR retrait" link to `/orders/{orderId}/pickup`.

For `completed`, the page shows a disabled final-state control labelled "Retrait finalisé".

For statuses before `ready`, the page keeps the existing disabled waiting state: "QR retrait — disponible quand la commande est prête".

## User Experience

The customer no longer sees a promise that a QR code will become available after the order has already been recovered.

The page remains visually stable because the bottom action area is still present on mobile and desktop.

## Testing

Add a frontend regression test for the customer order detail page:

- when the mocked order status is `completed`;
- the page shows "Retrait finalisé";
- the page does not show "disponible quand prête" or "disponible quand la commande est prête";
- the page does not expose the active "Afficher le QR retrait" link.

## Scope

No backend change, no API contract change, no data migration, no new dependency.
