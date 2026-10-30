<?php

namespace App\Exports;

class ImportTemplateDefinition
{
    public const STATES = [
        ['Jammu and Kashmir', '01'], ['Himachal Pradesh', '02'], ['Punjab', '03'], ['Chandigarh', '04'],
        ['Uttarakhand', '05'], ['Haryana', '06'], ['Delhi', '07'], ['Rajasthan', '08'], ['Uttar Pradesh', '09'],
        ['Bihar', '10'], ['Sikkim', '11'], ['Arunachal Pradesh', '12'], ['Nagaland', '13'], ['Manipur', '14'],
        ['Mizoram', '15'], ['Tripura', '16'], ['Meghalaya', '17'], ['Assam', '18'], ['West Bengal', '19'],
        ['Jharkhand', '20'], ['Odisha', '21'], ['Chhattisgarh', '22'], ['Madhya Pradesh', '23'], ['Gujarat', '24'],
        ['Dadra and Nagar Haveli and Daman and Diu', '26'], ['Maharashtra', '27'], ['Karnataka', '29'], ['Goa', '30'],
        ['Lakshadweep', '31'], ['Kerala', '32'], ['Tamil Nadu', '33'], ['Puducherry', '34'],
        ['Andaman and Nicobar Islands', '35'], ['Telangana', '36'], ['Andhra Pradesh', '37'], ['Ladakh', '38'],
        ['Other Territory', '97'], ['Other Country', '99'],
    ];

    public static function for(string $entity): array
    {
        return match ($entity) {
            'customers' => self::customers(),
            'items' => self::items(),
            default => throw new \InvalidArgumentException('Unsupported import template.'),
        };
    }

    public static function lists(): array
    {
        return [
            'states' => self::STATES,
            'units' => ['Nos', 'Pcs', 'Hrs', 'Kg', 'Ltr', 'Month', 'Year', 'Sqft'],
            'gst_rates' => [0, 5, 12, 18, 28],
            'item_types' => ['goods', 'service'],
        ];
    }

    private static function customers(): array
    {
        $fields = [
            ['Customer Name *', true, 'Name shown on invoices.', 'Aarav Sample Traders', 'Do not put separate first and last names.'],
            ['GSTIN', false, '15-character GST registration number, if registered.', '27ABCDE1234F1Z5', 'Remove spaces; keep all 15 characters.'],
            ['PAN', false, '10-character PAN, if available.', 'ABCDE1234F', 'Use letters and digits without spaces.'],
            ['Email', false, 'Customer contact email.', 'accounts@example.in', 'Check for missing @ or spaces.'],
            ['Phone', false, 'Customer phone number.', '9876543210', 'Use digits with optional +91; avoid scientific notation.'],
            ['Billing Address', false, 'Billing street/address.', '12 MG Road', 'Keep address text in one cell.'],
            ['Billing City', false, 'Billing city.', 'Pune', 'Use the city name, not the state.'],
            ['Billing State', false, 'Billing state or union territory.', 'Maharashtra', 'Choose from the dropdown when using Excel.'],
            ['Billing State Code', false, 'Two-digit GST state code.', '27', 'Keep the leading zero for codes such as 01.'],
            ['Billing Pincode', false, 'Billing PIN code.', '411001', 'Store as text to preserve leading zeros.'],
            ['Shipping Address', false, 'Shipping street/address.', '8 River View Road', 'Leave blank if same as billing.'],
            ['Shipping City', false, 'Shipping city.', 'Mumbai', 'Use the city name, not the state.'],
            ['Shipping State', false, 'Shipping state or union territory.', 'Maharashtra', 'Choose from the dropdown when using Excel.'],
            ['Shipping State Code', false, 'Two-digit GST state code.', '27', 'Keep the leading zero for codes such as 01.'],
            ['Credit Limit', false, 'Whole-number credit limit in rupees.', '50000', 'Use a number without currency symbols or commas.'],
            ['Payment Terms (days)', false, 'Number of days allowed for payment.', '30', 'Use a whole number; defaults to 30 in the app.'],
            ['Notes', false, 'Internal notes for this customer.', 'Call before dispatch', 'Do not put formulas in this cell.'],
            ['Active?', false, 'Whether this customer can be selected for new invoices.', 'Yes', 'Use Yes or No; defaults to Yes.'],
        ];

        return [
            'entity' => 'customers',
            'label' => 'Customers',
            'fields' => $fields,
            'examples' => [
                ['Aarav Sample Traders', '27ABCDE1234F1Z5', 'ABCDE1234F', 'accounts@aarav-example.in', '9876543210', '12 MG Road', 'Pune', 'Maharashtra', '27', '411001', '12 MG Road', 'Pune', 'Maharashtra', '27', 50000, 30, 'Sample row - remove before upload', 'Yes'],
                ['Meera Sample Foods', '29FGHIJ5678K1Z2', 'FGHIJ5678K', 'billing@meera-example.in', '9123456780', '45 Residency Road', 'Bengaluru', 'Karnataka', '29', '560025', '45 Residency Road', 'Bengaluru', 'Karnataka', '29', 25000, 15, 'Sample row - remove before upload', 'Yes'],
            ],
            'notes' => [
                'The current customers table has no customer-type, company-name, country, or opening-balance field. Those columns are not included; GSTIN is optional in the current application.',
                'Only Customer Name is required by the current customer form. Blank optional cells are allowed.',
            ],
        ];
    }

    private static function items(): array
    {
        $fields = [
            ['Item Name *', true, 'Name shown on invoices.', 'Premium Basmati Rice', 'Do not leave this blank.'],
            ['Item Code', false, 'Optional code/SKU; unique within this workspace.', 'RICE-001', 'Do not reuse a code already used in this workspace.'],
            ['HSN/SAC', false, 'Goods HSN or service SAC code.', '10063010', 'Enter the code as text; confirm it for your product/service.'],
            ['Description', false, 'Optional invoice line description.', 'Premium aged basmati rice', 'Keep description text in one cell.'],
            ['Type *', true, 'Choose whether this is a good or service.', 'goods', 'Use goods or service.'],
            ['Selling Price *', true, 'Default selling rate in rupees.', '85.50', 'Use a number without ₹ or commas.'],
            ['GST Rate (%) *', true, 'GST rate applied by default.', '5', 'Choose 0, 5, 12, 18, or 28.'],
            ['Unit *', true, 'Unit used on invoice lines.', 'Kg', 'Choose a unit from the dropdown.'],
            ['Active?', false, 'Whether the item can be selected on new invoices.', 'Yes', 'Use Yes or No; defaults to Yes.'],
        ];

        return [
            'entity' => 'items',
            'label' => 'Items and services',
            'fields' => $fields,
            'examples' => [
                ['Premium Basmati Rice', 'RICE-001', '10063010', 'Premium aged basmati rice', 'goods', 85.50, 5, 'Kg', 'Yes'],
                ['IT Support Services', 'SVC-001', '998313', 'Remote IT support per hour', 'service', 1500, 18, 'Hrs', 'Yes'],
            ],
            'notes' => [
                'The current items table has no purchase-price, discount, or opening-stock fields. These are not included.',
                'The current form requires Item Name, Type, Selling Price, GST Rate, and Unit. Code is optional and must be unique within the workspace.',
            ],
        ];
    }
}
