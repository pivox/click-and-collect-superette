# Domain Model

## Shop

- id
- name
- address
- phone
- qr_code
- locale
- currency

## Product

- id
- shop_id
- name_fr
- name_ar
- price_tnd
- active

## Kadhia

- id
- customer_id
- shop_id
- total_tnd

## KadhiaLine

- product_id
- quantity
- unit_price_tnd

## PickupSlot

- id
- shop_id
- start_at
- end_at
- capacity

## Order

- id
- customer_id
- shop_id
- slot_id
- status
- total_tnd

## OrderLine

- product_id
- quantity
- price_tnd

## PickupSession

- id
- order_id
- qr_token
- merchant_validated_at
- customer_validated_at
- completed_at
