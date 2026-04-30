# Event Storming

## Domain Events

- ShopQrCodeScanned
- ShopSessionStarted
- CatalogViewed
- ProductAddedToKadhia
- ProductRemovedFromKadhia
- PickupSlotSelected
- OrderSubmitted
- OrderAccepted
- OrderRejected
- OrderPreparationStarted
- ProductMarkedPrepared
- OrderReadyForPickup
- PickupQrCodeGenerated
- CustomerArrived
- PickupQrCodePresented
- MerchantPickupValidated
- CustomerPickupValidated
- OrderCompleted
- OrderCancelled

---

## Commands

- ScanShopQrCode
- StartShopSession
- AddProductToKadhia
- RemoveProductFromKadhia
- SelectPickupSlot
- SubmitOrder
- AcceptOrder
- RejectOrder
- StartOrderPreparation
- MarkProductPrepared
- MarkOrderReady
- GeneratePickupQrCode
- ValidateMerchantPickup
- ValidateCustomerPickup
- CompleteOrder
- CancelOrder

---

## Policies

### Policy 1
When `OrderAccepted`
Then `StartOrderPreparation`

### Policy 2
When `OrderPreparationStarted`
Then `GeneratePickupQrCode`

### Policy 3
When `MerchantPickupValidated` and `CustomerPickupValidated`
Then `CompleteOrder`

### Policy 4
When pickup deadline expired
Then `CancelOrder`
