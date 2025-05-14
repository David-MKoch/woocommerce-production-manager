# WooCommerce Production Manager

A comprehensive WooCommerce plugin to manage production capacity, delivery dates, order item statuses, reports, SMS notifications, and REST API integration.

## Features
- **Production Capacity Management**: Set and track capacity for product categories, products, and variations.
- **Delivery Date Calculation**: Automatically calculate delivery dates based on capacity and holidays, displayed at checkout.
- **Order Item Status Management**: Track and update statuses for individual order items with customizable statuses.
- **Customer Status Tracker**: Allow customers to view order item statuses via a dedicated frontend page.
- **Reports**: Generate reports for category orders, full capacity days, and status logs with Excel export.
- **SMS Notifications**: Send SMS notifications for order item status changes (requires an SMS provider API).
- **REST API**: Access capacity, order items, and reports via REST API with API key authentication.
- **Webhook Support**: Receive real-time notifications for order item status changes.
- **Persian Calendar Support**: Display dates in Persian (Jalali) calendar for Iran-based stores.

## Requirements
- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Composer (for installing dependencies)
- MySQL 5.7 or higher

## Installation
1. **Download the Plugin**:
   - Download the `woocommerce-production-manager.zip` file from the latest release.
2. **Install via WordPress**:
   - Navigate to `Plugins > Add New > Upload Plugin` in your WordPress admin panel.
   - Upload the ZIP file and click `Install Now`.
   - Activate the plugin.
3. **Install Dependencies**:
   - Run `composer install` in the plugin directory (`wp-content/plugins/woocommerce-production-manager/`) to install required PHP libraries (`morilog/jalali`, `phpoffice/phpspreadsheet`).
   - Alternatively, use the pre-built ZIP with the `vendor/` directory included.
4. **Verify WooCommerce**:
   - Ensure WooCommerce is installed and activated.

## Configuration
1. **Access Settings**:
   - Go to `WooCommerce > Production Manager` in the WordPress admin panel.
2. **General Settings**:
   - Set default delivery days and customize order item statuses with names and colors.
3. **Holidays**:
   - Define weekly holidays and custom holiday dates to exclude from delivery calculations.
4. **SMS Notifications**:
   - Enable SMS, enter your SMS provider API key, and customize the SMS template.
   - Customers can opt-in/out of SMS notifications via their account page.
5. **API & Webhook**:
   - Generate an API key for REST API authentication.
   - Configure a webhook URL to receive order item status change notifications.
6. **Production Capacity**:
   - Set maximum production capacity for categories, products, or variations in the settings tab.

## Usage
- **Admin Interface**:
  - Manage order item statuses and delivery dates under `WooCommerce > Order Items`.
  - View reports under `WooCommerce > Production Reports` (category orders, full capacity days, status logs).
  - Export reports and logs to Excel.
  - View and filter status logs under `WooCommerce > Status Logs`.
- **Customer Interface**:
  - Customers can view order item statuses at `/my-account/order-status/` (requires login).
  - Customers can enable/disable SMS notifications in their account settings.
- **Checkout**:
  - Estimated delivery dates are displayed for each product based on capacity and holidays.

## REST API
The plugin provides a REST API for accessing data. Use the API key in the `X-API-Key` header for authentication.

### Endpoints
- **GET `/wp-json/wpm/v1/capacity`**:
  - Parameters: `date`, `entity_type` (category, product, variation), `entity_id`, `persian_date` (boolean).
  - Returns: Capacity details for the specified date and entity.
- **GET `/wp-json/wpm/v1/order-items`**:
  - Parameters: `status`, `category`, `date_from`, `date_to`, `persian_date` (boolean).
  - Returns: List of order items with filters.
- **GET `/wp-json/wpm/v1/reports/{type}`**:
  - Types: `category-orders`, `full-capacity`.
  - Parameters: `date_from`, `date_to`, `persian_date` (boolean).
  - Returns: Report data based on the type.

### Webhook
- Configure a webhook URL in the settings to receive JSON payloads for order item status changes.
- Payload includes: `event`, `order_id`, `order_item_id`, `item_name`, `old_status`, `new_status`, `delivery_date`, `order_date`, `timestamp`.

## SMS Integration
- The plugin supports SMS notifications via an external SMS provider.
- Replace the sample API URL in `src/SMS/SMS.php` with your provider’s API endpoint (e.g., Kavenegar, Melipayamak).
- Example payload:
  ```json
  {
    "api_key": "your_api_key",
    "to": "customer_phone_number",
    "message": "Order #123 status changed to Processing."
  }
  ```

## Persian Date Support
- Dates are displayed in the Persian (Jalali) calendar by default.
- Use the `persian_date=false` parameter in API requests to get Gregorian dates.
- The plugin uses the `morilog/jalali` library for date conversions.

## Troubleshooting
- **Composer Errors**: Ensure Composer is installed and run `composer install` in the plugin directory.
- **API Authentication Issues**: Verify the API key in `WooCommerce > Production Manager > Webhook & API`.
- **SMS Not Sending**: Check the SMS provider API key and template settings. Ensure the customer has a valid phone number and SMS notifications enabled.
- **Date Issues**: Ensure the server timezone is set correctly in WordPress settings.

## Support
- **Documentation**: Available in the plugin settings under `WooCommerce > Production Manager`.
- **Issues**: Report bugs or request features via the GitHub repository (if available) or contact the developer.
- **Customizations**: For custom SMS provider integrations or additional features, contact the developer.

## License
This plugin is licensed under the GPL-2.0+ license.

## Credits
- Built with [morilog/jalali](https://github.com/morilog/jalali) for Persian date support.
- Uses [phpoffice/phpspreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) for Excel exports.
- Persian Datepicker by [persian-datepicker](https://github.com/behzadi/persianDatepicker).

---
**Developed by Your Name**