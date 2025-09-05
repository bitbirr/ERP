# Finance Accounts Management Manual

## Overview

This manual provides comprehensive guidance on managing Bank Accounts and General Ledger (GL) Accounts within our ERP system. These two modules work together to provide complete financial accounting functionality, from bank reconciliation to detailed financial reporting.

## Key Differences Between Bank Accounts and GL Accounts

### Bank Accounts
- **Purpose**: Represent actual bank accounts used for cash management and reconciliation
- **Structure**: Flat structure with direct bank account details
- **Functionality**: Track actual cash flows, balances, and bank transactions
- **Usage**: Daily banking operations, cash flow management, bank reconciliation
- **Relationship**: Each bank account is linked to a GL account for accounting purposes

### GL Accounts
- **Purpose**: Chart of accounts for double-entry bookkeeping and financial reporting
- **Structure**: Hierarchical tree structure with parent-child relationships
- **Functionality**: Record all financial transactions through journal entries
- **Usage**: Financial reporting, budgeting, compliance, audit trails
- **Relationship**: GL accounts can be linked to multiple bank accounts or used independently

## Relationships and Integration

### Core Relationships
- **Bank Account → GL Account**: Each bank account must be linked to a GL account
- **GL Account → Bank Accounts**: One GL account can be linked to multiple bank accounts
- **GL Lines → GL Accounts**: Journal entries post to GL accounts
- **Telebirr Transactions → Bank Accounts**: Mobile money transactions flow through bank accounts

### Business Logic Flow
1. **Transaction Initiation**: Business transactions (sales, purchases, payments) create journal entries
2. **GL Posting**: Transactions post to appropriate GL accounts
3. **Bank Reconciliation**: Actual bank transactions are recorded against bank accounts
4. **Financial Reporting**: GL accounts provide the basis for all financial statements

## Module Dependencies and Integrations

### TeleBirr Integration
- **Bank Accounts**: Store TeleBirr agent transactions and balances
- **GL Accounts**: Record TeleBirr revenue, fees, and settlements
- **Usage Scenarios**:
  - Agent top-ups and repayments
  - Commission tracking
  - Settlement processing

### Branch Management
- **Multi-branch Support**: Both modules support branch-specific accounts
- **Branch-wise Reporting**: Financial data can be filtered by branch
- **Usage Scenarios**:
  - Branch-specific bank accounts
  - Branch-wise GL account hierarchies
  - Inter-branch transfers

### Product and Inventory
- **GL Integration**: Inventory transactions post to GL accounts
- **Cost Accounting**: Product costs flow through GL for accurate valuation
- **Usage Scenarios**:
  - Cost of goods sold tracking
  - Inventory valuation
  - Product profitability analysis

### Sales and Revenue
- **Revenue Recognition**: Sales transactions automatically post to revenue GL accounts
- **Customer Integration**: Customer-specific GL tracking for receivables
- **Usage Scenarios**:
  - Sales revenue recording
  - Customer account management
  - Revenue forecasting

### Reports and Analytics
- **Financial Statements**: GL accounts form the basis of balance sheets and P&L
- **Bank Reconciliation Reports**: Compare bank account balances with GL
- **Usage Scenarios**:
  - Monthly financial reporting
  - Budget vs actual analysis
  - Cash flow projections

## Bank Accounts Management

### Accessing Bank Accounts
1. Navigate to **Finance → Bank Accounts** in the main menu
2. The system displays a dashboard with account summary metrics
3. Use tabs to view All Accounts, Active Only, Inactive Only, or Audit Logs

### Creating a New Bank Account
1. Click the **"Add Account"** button
2. Fill in the required fields:
   - **Account Name**: Descriptive name for the account
   - **External Number**: Bank's reference number
   - **Account Number**: Internal account number
   - **GL Account ID**: Link to corresponding GL account
   - **Account Type**: Checking, Savings, Credit, or Loan
   - **Initial Balance**: Opening balance (if applicable)
   - **Branch**: Associated branch
   - **Customer**: Associated customer (if applicable)
3. Click **"Create"** to save the account

### Managing Bank Accounts
- **View Details**: Click the view icon to see complete account information
- **Edit Account**: Click the edit icon to modify account details
- **Delete Account**: Click the delete icon (only available for zero-balance accounts)
- **Status Management**: Toggle active/inactive status

### Advanced Features
- **Filtering**: Use advanced filters by type, status, balance range, dates
- **Search**: Search by account name, GL number, or account number
- **Audit Trail**: View complete audit logs of all account changes
- **Balance Tracking**: Real-time balance updates from transactions

## GL Accounts Management

### Accessing GL Accounts
1. Navigate to **Finance → GL Accounts** in the main menu
2. Choose between **Dashboard** (summary view) or **Accounts List** (detailed view)

### Creating a New GL Account
1. Click the floating **"+"** button
2. Fill in the required fields:
   - **Account Name**: Descriptive name
   - **Type**: Asset, Liability, Equity, Revenue, or Expense
   - **Normal Balance**: Debit or Credit
   - **Parent Account**: Link to parent account (for hierarchical structure)
   - **Postable**: Whether transactions can post directly to this account
3. Click **"Create"** to save the account

### Managing GL Accounts
- **View Details**: Click the view icon for complete account information
- **Edit Account**: Click the edit icon to modify account properties
- **View Balance**: Click the balance icon to see current account balance
- **Delete Account**: Click the delete icon (only for accounts without transactions)

### Advanced Features
- **Hierarchical Structure**: Create parent-child account relationships
- **Account Tree**: View accounts in hierarchical tree format
- **Balance Calculation**: Real-time balance computation from posted journals
- **Multi-branch Support**: Branch-specific account management

## Step-by-Step Guides

### Bank Reconciliation Process
1. **Record Bank Transactions**: Import or manually enter bank statements
2. **Match Transactions**: Match bank transactions to GL journal entries
3. **Reconcile Differences**: Identify and resolve discrepancies
4. **Post Adjustments**: Create adjusting journal entries as needed
5. **Complete Reconciliation**: Mark the reconciliation as complete

### Journal Entry Creation
1. **Access Journals**: Navigate to Finance → Journals
2. **Create New Journal**: Click "New Journal" button
3. **Enter Header Information**: Date, reference, description
4. **Add Journal Lines**: Select GL accounts and enter debit/credit amounts
5. **Validate Entry**: Ensure debits equal credits
6. **Post Journal**: Submit for posting to GL accounts

### Financial Reporting
1. **Access Reports**: Navigate to Reports → Financial Reports
2. **Select Report Type**: Balance Sheet, P&L, Cash Flow, etc.
3. **Set Parameters**: Date range, branch, account filters
4. **Generate Report**: Click "Generate" to produce the report
5. **Export/Print**: Export to PDF or Excel format

## Best Practices

### Account Setup
- Use consistent naming conventions for accounts
- Create hierarchical GL account structures for better organization
- Link bank accounts to appropriate GL accounts immediately
- Set up accounts by branch for multi-branch operations

### Transaction Processing
- Always validate journal entries before posting
- Use descriptive memos for all transactions
- Maintain proper audit trails for all changes
- Regular reconciliation of bank accounts

### Security and Access
- Implement role-based access controls
- Regular backup of financial data
- Monitor user access and changes through audit logs
- Use approval workflows for high-value transactions

## Troubleshooting

### Common Issues
- **Balance Discrepancies**: Check for unposted journals or missing transactions
- **Account Linking Errors**: Ensure GL accounts exist before linking bank accounts
- **Posting Failures**: Validate journal entry balances and account status
- **Permission Errors**: Check user roles and permissions

### Support Resources
- **User Documentation**: Refer to this manual for detailed procedures
- **System Logs**: Check application logs for technical issues
- **Audit Trails**: Use audit logs to track changes and identify issues
- **Technical Support**: Contact IT support for system-related problems

## Integration Workflows

### Sales to Finance Integration
1. Sales transaction is completed
2. System automatically creates journal entry:
   - Debit: Accounts Receivable (customer account)
   - Credit: Revenue account
3. Inventory adjustment posted if applicable
4. GL accounts updated in real-time

### TeleBirr Transaction Flow
1. TeleBirr transaction initiated
2. Transaction recorded in Telebirr system
3. Bank account balance updated
4. GL journal entry created automatically:
   - Debit/Credit: Bank account GL
   - Debit/Credit: Revenue/Expense accounts
5. Settlement processed through bank reconciliation

### Inventory to Finance Integration
1. Inventory movement occurs (sale, purchase, adjustment)
2. Cost of goods sold calculated
3. Journal entries created:
   - Debit: Cost of Goods Sold
   - Credit: Inventory account
4. GL accounts reflect current inventory valuation

This manual provides the foundation for effective financial management within our ERP system. Regular review and updates will ensure continued alignment with business processes and regulatory requirements.