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
- CustomerNotifiedOrderReady
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
- NotifyCustomerOrderReady
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
When `MarkOrderReady`
Then the merchant may optionally trigger `NotifyCustomerOrderReady`

Notification is useful but not mandatory. The order can still continue to pickup without this notification.

### Policy 4
When `MerchantPickupValidated` and `CustomerPickupValidated`
Then `CompleteOrder`

### Policy 5
When pickup deadline expired
Then `CancelOrder`
