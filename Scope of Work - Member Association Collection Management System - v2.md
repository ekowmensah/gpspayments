# Scope of Work (SOW) v2
## Member Association Collection Management System

Version: 2.0  
Date: May 21, 2026  
Prepared for: [Association Name]  
Prepared by: [Vendor/Team Name]

## 1. Purpose and Objectives
This SOW defines the design, development, testing, deployment, and handover of a Member Association Collection Management System to manage:
- Member registration and records
- Dues, levies, welfare contributions, and donations
- Payment collection and reconciliation
- Arrears tracking and reminders
- Receipts and financial/member reporting

Primary outcomes:
- Improve collection rate and payment visibility
- Reduce manual reconciliation errors
- Provide accurate and auditable financial reporting
- Enable role-based governance and accountability

## 2. Scope
### 2.1 In Scope
- Web-based application for administrators, finance officers, secretaries, auditors/chairpersons, and optional member self-service access
- Member and group/branch management
- Collection item setup and assignment
- Payment recording and integration-ready payment callback processing
- Arrears and statement computation
- Receipt generation and verification
- Dashboard and report generation with export
- SMS reminder capability (subject to SMS provider setup)
- User/role management and permissions
- Audit logs, backups, and core settings

### 2.2 Out of Scope
- Native mobile app development (unless added by change request)
- Full accounting/ERP module beyond collection and reconciliation reports
- Biometric verification
- Third-party provider onboarding fees
- Payment gateway license/commercial fees

## 3. User Roles and Access Model
Roles covered:
- Administrator
- Treasurer / Finance Officer
- Secretary
- Auditor / Chairman (read-only oversight)
- Member (optional portal role)

Role permissions shall be implemented through granular RBAC with action-level rights:
- `view`, `create`, `edit`, `delete`, `approve`, `void`, `refund`, `export`, `send_notification`, `manage_settings`

Segregation of duties controls:
- User recording a payment cannot approve its void/refund.
- High-risk actions (`void`, `refund`, `role change`, `backdate posting`) require approval or elevated privilege.

## 4. Functional Requirements
### 4.1 Member Management
System shall support:
- Member profile creation and update
- Member ID (auto-generated or manual, unique)
- Member demographic/contact fields
- Branch/group assignment
- Status management (`Active`, `Inactive`, `Suspended`, `Exited`, `Deceased`)
- Next-of-kin details
- Photo upload (and camera capture on supported devices)
- Bulk import via CSV template with row-level validation feedback

### 4.2 Groups / Branches / Zones
- Configurable hierarchy for association subdivisions
- Optional module enable/disable
- Assignment of members and collection policies by group

### 4.3 Collection Items (Dues/Levies/Contributions)
Each item includes:
- Name, description, amount
- Frequency (`One-time`, `Monthly`, `Quarterly`, `Yearly`, `Voluntary`)
- Required/optional flag
- Effective start date and due date rule
- Applicability (`All members` or selected groups/members)
- Status (`Draft`, `Active`, `Paused`, `Archived`)

### 4.4 Payment Collection
Supported methods:
- Cash
- Mobile Money
- Bank transfer
- Card and USSD (optional based on gateway readiness)

Payment record includes:
- Member/donor reference
- Collection item
- Amount and currency
- Method and channel
- External transaction reference
- Internal payment reference
- Receipt number
- Payment date/time
- Recorded by / approved by
- Notes

### 4.5 Arrears and Statement Management
System computes per member and period:
- Expected amount
- Paid amount
- Outstanding balance
- Months unpaid
- Partial vs fully paid state
- Defaulter indicators

### 4.6 Receipt Management
- Auto-generated receipt for every successful posted payment
- Printable and downloadable receipt
- Receipt verification screen by receipt number/QR
- Optional QR code support

### 4.7 Donations
Donation support for:
- Member donations
- Non-member donations
- Anonymous donations
- Project-specific and general-purpose donations

## 5. Financial and Business Rules
The following rules must be finalized during requirements workshop and configured in system settings:

1. Due generation cadence:
- Recurring dues are generated on configured cycle date.

2. Proration policy:
- New members joining mid-cycle can be full-charge or prorated (configurable).

3. Grace period and penalty:
- Grace days and penalty/interest method are configurable.

4. Partial payment allocation:
- Default allocation order: oldest outstanding period first, then current period.

5. Overpayment handling:
- Overpayment may be held as credit and auto-applied to next due (configurable).

6. Reversal/void/refund:
- All reversals require reason capture and audit log entry.

7. Posting date vs payment date:
- Reports support both payment timestamp and financial posting date.

## 6. Payment Integration and Reconciliation
### 6.1 Integration Model
System shall support:
- Manual entry mode
- Callback/webhook mode for external providers

### 6.2 Idempotency and Duplicate Prevention
- Callback events must use idempotency key (`provider_txn_ref + amount + channel` or equivalent).
- Duplicate callbacks must not create duplicate payments.

### 6.3 Settlement and Reconciliation
- Separate payment capture from settlement confirmation.
- Daily reconciliation report showing:
- provider total
- system total
- matched items
- unmatched items
- variance and status

## 7. Dashboard and Reporting
### 7.1 Dashboard KPIs
- Total members
- Active members
- Total expected (selected period)
- Total collected (selected period)
- Outstanding balance
- Today’s collection
- Monthly collection
- Defaulters count
- Donations total
- Collection rate

### 7.2 Reports
Member reports:
- All/active/inactive members
- Members by branch/group
- Fully paid/partial/owing members

Financial reports:
- Daily/weekly/monthly/yearly collections
- Collection item reports (dues/levies/donations)
- Channel reports (cash/MoMo/bank/card)
- Arrears report
- Collector performance
- Reconciliation variance report

Exports:
- Print, PDF, Excel, CSV

Report controls:
- Date range, branch/group filter, item filter, channel filter
- Timezone-aware cut-off settings

## 8. Notifications
- SMS reminder templates and scheduling
- Trigger-based reminders (upcoming due, overdue, receipt sent)
- Notification logs with delivery status where available from provider

## 9. Non-Functional Requirements (NFRs)
### 9.1 Performance
- 95th percentile page response <= 2.5 seconds under agreed baseline load
- Dashboard load <= 3 seconds for up to 10,000 members (baseline dataset)

### 9.2 Availability and Reliability
- Target uptime: 99.5% monthly (excluding scheduled maintenance)
- Backup schedule: daily automated backups
- Retention: minimum 30 days rolling backups

### 9.3 Security
- TLS for all in-transit traffic
- Encryption at rest for sensitive data where platform supports it
- Password policy enforcement
- Optional MFA support for privileged roles
- Session timeout and account lockout controls
- Immutable audit logs for critical actions

### 9.4 Data Integrity
- Referential integrity across members, charges, payments, receipts
- Non-editable posted financial records; corrections through reversal workflow

## 10. Audit, Compliance, and Governance
- Full audit trail for create/update/delete/void/refund/role changes/settings changes
- Audit logs include actor, timestamp, IP/device metadata (where available), before/after values
- Data retention policy configurable for operational and legal needs
- Consent capture/check for communication channels where required

## 11. Data Migration and Import
- Initial data import templates for members and opening balances
- Validation rules and error report on upload
- Dry-run import mode before commit
- Migration sign-off required before go-live

## 12. Environments and DevOps
- Separate environments: Development, UAT/Staging, Production
- Release workflow with change log and rollback procedure
- Monitoring and alerting for:
- failed callbacks
- failed jobs
- backup failures
- unusual payment anomalies

## 13. Testing and Acceptance
### 13.1 Test Coverage
- Unit tests for core calculation logic
- Integration tests for payment callbacks and reconciliation
- User acceptance testing (UAT) across all roles

### 13.2 Acceptance Criteria (Minimum)
1. Member lifecycle:
- Create, update, deactivate, and report members successfully.

2. Collection lifecycle:
- Create recurring and one-time items, assign scope, and generate expected charges correctly.

3. Payment lifecycle:
- Record payment, issue receipt, update statement, and reflect in dashboard/reports.

4. Arrears accuracy:
- Expected, paid, balance, and status calculations match approved test cases.

5. Security and governance:
- Role restrictions enforced; audit logs captured for critical actions.

6. Reconciliation:
- System detects matched/unmatched transactions and outputs variance report.

## 14. Implementation Plan and Milestones
Proposed phased delivery:
1. Discovery and detailed requirements sign-off
2. Solution design and data model approval
3. Core module implementation (members, collections, payments, receipts)
4. Arrears, reports, and dashboard
5. Integrations and reconciliation
6. UAT and defect resolution
7. Production deployment and hypercare support

Final milestone dates and effort estimates shall be published in the project plan.

## 15. Assumptions and Dependencies
- Client provides timely feedback and sign-off at each milestone.
- Payment/SMS provider credentials and documentation are supplied.
- Required hosting/infrastructure is provisioned before deployment window.
- Data source files for migration are complete and in agreed format.

## 16. Change Control
- Any scope change must be documented through a change request (CR).
- CR includes impact to timeline, cost, and technical risk.
- Work proceeds only after written approval.

## 17. Deliverables
- Configured and deployed web application
- Source code and deployment package (as contracted)
- Database schema and migration scripts
- User guide and admin guide
- Test evidence and UAT sign-off records
- Handover and support transition documentation

## 18. Sign-off
Prepared by: ____________________  
Date: ____________________

Approved by (Client): ____________________  
Date: ____________________

