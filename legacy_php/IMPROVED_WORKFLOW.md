# Improved Payment Workflow

## Overview
This document details the enhanced payment processing workflow with critical improvements over the basic version:
- **Payment method routing** (cash vs. digital)
- **Verification checkpoints** (duplicate detection, validation)
- **Reconciliation procedures** (cash reconciliation, digital reconciliation)
- **Audit trails** (complete tracking for compliance)
- **Member notifications** (SMS/email confirmations)
- **Error handling** (retries, failed payment management)
- **Overpayment handling** (credit tracking)

---

## Payment Method Routes

### Route 1: Cash Payment
```
Member pays cash (in-person) 
   ↓
Treasurer collects and records receipt number
   ↓
Treasurer enters payment details in system:
   - Member ID/Name
   - Collection Item
   - Amount received
   - Payment method: CASH
   - Receipt #
   - Date/Time
   - Notes (if any)
   ↓
System performs VERIFICATION (see: Verification Section)
   ↓
If verification FAILS → Show error, allow correction
   ↓
If verification PASSES → Payment marked PENDING_RECONCILIATION
   ↓
[RECONCILIATION: End-of-day or mid-day batch]
Treasurer reconciles:
   - Count physical cash
   - Verify vs. system recorded amount
   - If match → Mark CONFIRMED
   - If discrepancy → Flag for review, hold payment
   ↓
Once reconciliation CONFIRMED → System generates receipt
   ↓
Member receives SMS: "GHS X.XX received for [Item]. Ref: [#]"
   ↓
Payment updates member statement & arrears
   ↓
Audit log created: PAYMENT_CONFIRMED, by [Treasurer], at [Time]
   ↓
Dashboard refreshes
```

### Route 2: Mobile Money Payment
```
Member initiates MoMo payment via external provider (MTN, Vodafone, etc.)
   ↓
Member completes transaction on MoMo platform
   ↓
Payment gateway sends callback to system with:
   - Transaction ID
   - Member reference (if provided)
   - Amount
   - Status (success/failed)
   - Timestamp
   ↓
System performs VERIFICATION (see: Verification Section)
   ↓
If verification FAILS → Log error, notify admin, no payment recorded
   ↓
If verification PASSES → System auto-reconciles with MoMo database
   - Confirms transaction exists in provider system
   - Confirms amount matches expected
   - Marks as VERIFIED
   ↓
Payment marked CONFIRMED (no manual reconciliation needed)
   ↓
System generates receipt
   ↓
Member receives SMS: "GHS X.XX received for [Item]. Ref: [#]"
   ↓
Payment updates member statement & arrears
   ↓
Audit log created: PAYMENT_CONFIRMED_AUTO, via MoMo, at [Time]
   ↓
Dashboard refreshes
```

### Route 3: Bank Transfer
```
Member transfers to association account
   ↓
Admin/Treasurer manually matches transfer to member:
   - Bank statement shows: Amount + Date + Payer name/ref
   - Admin enters payment in system:
     * Member ID/Name
     * Collection Item
     * Amount
     * Payment method: BANK_TRANSFER
     * Bank transaction reference
     * Bank statement attachment/screenshot
     * Date transfer received
   ↓
System performs VERIFICATION (see: Verification Section)
   ↓
If verification FAILS → Show error
   ↓
If verification PASSES → Payment marked PENDING_RECONCILIATION
   ↓
[RECONCILIATION]
Admin verifies bank statement shows transaction
   - If match → Mark CONFIRMED
   - If no match → Hold payment, flag for manual review
   ↓
Once reconciliation CONFIRMED → System generates receipt
   ↓
Member receives SMS: "GHS X.XX received for [Item]. Ref: [#]"
   ↓
Payment updates member statement & arrears
   ↓
Audit log created: PAYMENT_CONFIRMED, via Bank Transfer, at [Time]
   ↓
Dashboard refreshes
```

### Route 4: USSD (Manual Entry)
```
Member completes USSD transaction
   ↓
Member receives confirmation code from USSD provider
   ↓
Member (or Treasurer) enters payment in system:
   - Member ID
   - Collection Item
   - Amount
   - Payment method: USSD
   - USSD confirmation code
   - Timestamp
   ↓
System performs VERIFICATION (see: Verification Section)
   ↓
If verification FAILS → Show error
   ↓
If verification PASSES → Payment marked PENDING_VERIFICATION
   ↓
[VERIFICATION]
System attempts to verify USSD code with provider (if API available)
   - If confirmed → Mark as VERIFIED
   - If provider API unavailable → Manual verification required (admin confirms)
   ↓
Once VERIFIED → Payment marked CONFIRMED
   ↓
System generates receipt
   ↓
Member receives SMS: "GHS X.XX received for [Item]. Ref: [#]"
   ↓
Payment updates member statement & arrears
   ↓
Audit log created: PAYMENT_CONFIRMED, via USSD, at [Time]
   ↓
Dashboard refreshes
```

---

## Verification Stage

All payments must pass verification before moving to reconciliation.

### Duplicate Detection
Check if payment already exists:
- **Same Member + Collection Item + Amount within last 24 hours** = Potential duplicate
- Action: Show warning, require confirmation to proceed or reject
- Log attempt to prevent fraud

### Amount Validation
- Payment amount matches expected amount (or allow partial/overpayment with explicit flag)
- Amount is positive and reasonable (> 0, < collection item max)
- Currency is valid (GHS)

### Status Checks
- Member is ACTIVE (not suspended/deleted)
- Collection Item is ACTIVE
- Member assigned to this collection item
- Payment date is within valid range (not in future, not before collection start)

### Fraud Checks
- Same payment method not used >3 times by same member in 1 hour (burst detection)
- Payment amount doesn't exceed member's total expected amount by >50% (outlier detection)

### Result
- ✅ PASS → Proceed to reconciliation/confirmation
- ❌ FAIL → Block payment, show reason, allow corrections or contact admin

---

## Reconciliation & Confirmation

### Cash Reconciliation (Manual)
**Timing:** End-of-day (or mid-day for high-volume)

**Process:**
1. Treasurer pulls report: "Payments recorded today"
2. Treasurer physically counts cash collected
3. System shows: Total recorded vs. cash in hand
4. Reconciliation options:
   - ✅ **Match** → Click "Confirm all" → All payments CONFIRMED
   - ❌ **Mismatch** → Specify difference:
     - Overage (collected more than recorded) → Add missing payments
     - Shortage (collected less than recorded) → Mark payments as PENDING or REJECTED
     - Individual issues → Mark specific payments for review

5. Once all resolved → Mark reconciliation batch as CLOSED
6. System generates reconciliation report for audit

### Digital Reconciliation (Auto)
**For:** Mobile Money, USSD (provider-verified), Bank Transfer (confirmed receipt)

**Process:**
1. Payment callback received
2. System automatically cross-checks:
   - Transaction exists in provider/bank database
   - Amount matches
   - Member matches
3. If all match → Automatically marked CONFIRMED
4. If mismatch → Flagged for manual review

**Manual Override:**
- If auto-reconciliation fails, admin can manually verify and override status

---

## Error Handling

### Failed Payments
**Scenario:** Member attempts MoMo payment but transaction fails

**Process:**
1. Payment gateway returns failure status
2. System creates payment record with status: FAILED
3. Member receives SMS: "Payment failed. Please try again."
4. Member not charged
5. Payment doesn't affect arrears
6. Admin can retry or manually add alternative payment method

### Payment Timeout
**Scenario:** MoMo callback not received within expected time

**Process:**
1. System queues callback check (retry every 5 minutes for 1 hour)
2. If callback arrives late → Process normally
3. If callback never arrives → After 1 hour, mark as TIMEOUT
4. Manual review required by admin
5. Member can contact support to verify status

### Overpayment Handling

**Scenario:** Member pays more than owed

**Options:**
1. **Credit Account:** Excess amount applied to next month's dues
   - Payment confirmed
   - Arrears reduced
   - Member balance shows credit
   - SMS: "GHS X.XX credited to your account for future dues"

2. **Refund:** Return excess to member
   - Requires manual approval
   - Create refund record with tracking
   - SMS: "Refund of GHS X.XX being processed"

3. **Donation:** Treat excess as voluntary donation
   - Requires member consent
   - Log as donation
   - Receipt shows payment + donation

---

## Notifications

### SMS Triggers

| Event | Message Template | Recipient |
|-------|------------------|-----------|
| Payment Recorded | "GHS X received for [Item]. Ref:[#]. Thank you." | Member |
| Arrears Due | "Arrears alert: GHS X due for [Item]. Please pay by [Date]." | Member |
| Overpayment | "Overpayment of GHS X received. Credited to your account." | Member |
| Payment Confirmed | "Payment confirmed. Receipt [#] available online." | Member |
| Refund Initiated | "Refund of GHS X being processed to your account." | Member |
| Collection Assigned | "[Item] due: GHS X. Payment methods: [Cash/MoMo/etc]" | Member |

### Email Triggers
- Receipt PDF (when payment confirmed)
- Arrears statement (weekly/monthly)
- Payment history export (on request)

---

## Audit Trail Requirements

Every transaction must log:
- **Who:** User ID, Role (Treasurer/Admin)
- **What:** Action (Record, Verify, Confirm, Refund, etc.)
- **When:** Exact timestamp
- **Where:** Payment method, system entry point
- **Why:** Event reason (user action, auto-reconciliation, system rule, etc.)
- **Status:** Before/After state (PENDING → CONFIRMED)

### Example Log Entry
```
Payment ID: PAY-2026-051-001
Timestamp: 2026-05-21 14:35:22
User: treasurer_john (ID: 112)
Action: PAYMENT_CONFIRMED
Member: Kwame Mensah (ID: 45)
Amount: GHS 20.00
Method: CASH
Collection: Monthly Dues
Previous Status: PENDING_RECONCILIATION
New Status: CONFIRMED
Reference: CASH-2026-05-21-001
Notes: End-of-day reconciliation batch #3
---
Payment ID: PAY-2026-051-002
Timestamp: 2026-05-21 14:32:15
User: System
Action: PAYMENT_VERIFIED
Method: MoMo
Provider Ref: MTN-12345-67890
Status: VERIFIED (auto-reconciled)
```

---

## Summary: New vs. Old Workflow

| Aspect | Old Workflow | New Workflow |
|--------|------------|------------|
| **Payment Recording** | Ambiguous (record OR callback) | Explicit: route by method |
| **Verification** | None | Duplicate check, validation, fraud detection |
| **Reconciliation** | None | Cash: manual; Digital: auto |
| **Duplicates** | Risk of double-recording | Detected and prevented |
| **Member Notification** | No confirmation | SMS/Email sent immediately |
| **Overpayments** | Unhandled | Credit, refund, or donation options |
| **Error Handling** | Not addressed | Retries, timeouts, manual override |
| **Audit Trail** | Not mentioned | Complete tracking of all actions |
| **Confirmation Status** | Unclear when "confirmed" | Explicit CONFIRMED status after verification + reconciliation |
| **Accountability** | Low (who received cash?) | High (all actions tracked) |

---

## Implementation Notes

1. **Payment Status Enum:**
   - PENDING_ENTRY
   - PENDING_VERIFICATION
   - PENDING_RECONCILIATION
   - VERIFIED
   - CONFIRMED
   - FAILED
   - TIMEOUT
   - REFUNDED

2. **Database Tables Needed:**
   - `payments` (with status, method, verified_by, confirmed_by)
   - `payment_verifications` (duplicate checks, validation results)
   - `reconciliation_batches` (group cash payments by session)
   - `audit_logs` (every action)
   - `notifications` (SMS/email tracking)

3. **API Integrations:**
   - MoMo callback handling
   - Bank transaction verification (manual for now)
   - USSD confirmation (if provider API available)

4. **Admin Dashboard Additions:**
   - Reconciliation batches (pending, completed, errors)
   - Failed payment queue
   - Verification failures
   - Audit log viewer
