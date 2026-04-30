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
- ProductMarkedUnavailable
- ReplacementProductSuggested
- ReplacementProductAccepted
- ReplacementProductRejected
- OrderTotalAdjusted
- OrderPartiallyPrepared
- OrderReadyForPickup
- CustomerNotifiedOrderReady
- PickupQrCodeGenerated
- CustomerArrived
- CustomerArrivedEarly
- CustomerWaitingStarted
- CustomerWaitingEnded
- CustomerEtaShared
- CustomerEtaUpdated
- FreshPreparationScheduled
- FreshPreparationWindowOpened
- FreshProductsPreparationStarted
- FreshProductsPrepared
- PickupDelegationCreated
- PickupDelegationRevoked
- DelegatedPickupCodeGenerated
- DelegatedPersonArrived
- DelegatedPickupCodePresented
- DelegatedPickupValidated
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
- MarkProductUnavailable
- SuggestReplacementProduct
- AcceptReplacementProduct
- RejectReplacementProduct
- AdjustOrderTotal
- MarkOrderPartiallyPrepared
- MarkOrderReady
- NotifyCustomerOrderReady
- MarkCustomerArrived
- ShareCustomerEta
- UpdateCustomerEta
- ScheduleFreshPreparation
- OpenFreshPreparationWindow
- StartFreshPreparation
- MarkFreshProductsPrepared
- CreatePickupDelegation
- RevokePickupDelegation
- GenerateDelegatedPickupCode
- MarkDelegatedPersonArrived
- ValidateDelegatedPickupCode
- StartCustomerWaiting
- EndCustomerWaiting
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
When `ProductMarkedUnavailable`
Then the merchant can either remove the product from the order or suggest a replacement product.

The order total must be adjusted after removal or replacement.

### Policy 5
When `ReplacementProductSuggested`
Then the customer may accept or reject the replacement.

If the customer does not answer before preparation deadline, the merchant can continue with the unavailable product removed.

### Policy 6
When `CustomerArrived` and order status is not `ready`
Then `StartCustomerWaiting`

### Policy 7
When `OrderReadyForPickup` and customer is waiting
Then alert the merchant immediately and allow pickup validation.

### Policy 8
When `CustomerEtaShared` or `CustomerEtaUpdated`
Then recalculate `FreshPreparationScheduled` if the order contains fresh or frozen products.

### Policy 9
When `FreshPreparationWindowOpened`
Then alert the merchant to prepare fresh or frozen products at the last moment.

### Policy 10
When `CreatePickupDelegation`
Then `GenerateDelegatedPickupCode`

The delegated person can retrieve the order only with a valid delegated pickup code.

### Policy 11
When `DelegatedPickupCodePresented`
Then the merchant validates the code and checks the delegated person identity hint if configured.

### Policy 12
When `DelegatedPickupValidated` and `MerchantPickupValidated`
Then `CompleteOrder`

### Policy 13
When `MerchantPickupValidated` and `CustomerPickupValidated`
Then `CompleteOrder`

### Policy 14
When pickup deadline expired
Then `CancelOrder`
