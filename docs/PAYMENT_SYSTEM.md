# Payment System Documentation

## Overview
The payment system allows customers to pay for their reservations via GCash by scanning a QR code and uploading payment proof.

## Payment Flow

1. **Customer Books Reservation**
   - Customer fills out reservation form at `/reservations/reservation.php`
   - Selects services and appointment date/time
   - Submits the form

2. **Redirected to Payment Page**
   - After successful reservation, customer is redirected to `/reservations/payment.php`
   - Page displays:
     - Reservation summary
     - Total amount to pay
     - GCash QR code (placeholder for now)
     - Payment instructions

3. **Customer Makes Payment**
   - Customer scans QR code with GCash app
   - Sends the exact amount
   - Takes screenshot of payment confirmation
   - Uploads screenshot on payment page
   - Enters GCash account name and amount paid
   - Submits payment proof

4. **Payment Verification**
   - Payment status is set to "pending"
   - Admin can verify payments in admin panel
   - Once verified, reservation status changes to "confirmed"

## Database Schema

### Payments Table
```sql
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_proof` varchar(255) NOT NULL,
  `payment_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);
```

### Reservations Table Update
- Added `status` column with values:
  - `pending` - Initial status
  - `pending_verification` - Payment submitted, awaiting verification
  - `confirmed` - Payment verified
  - `completed` - Service completed
  - `cancelled` - Reservation cancelled

## File Structure

```
reservations/
├── reservation.php    # Booking form
└── payment.php        # Payment page

uploads/
└── payments/          # Payment proof screenshots stored here

database/
└── payments_table.sql # Database schema

scripts/
└── setup_payments.php # Setup script to create tables
```

## Setup Instructions

1. **Run Setup Script**
   ```
   http://localhost/Papsi/scripts/setup_payments.php
   ```
   This will:
   - Create the `payments` table
   - Add `status` column to `reservations` table
   - Create `uploads/payments/` directory

2. **Configure GCash QR Code**
   - Replace the QR code placeholder in `payment.php` (line ~260)
   - Add your actual GCash QR code image
   - Or integrate with GCash API for dynamic QR codes

## Features

### Payment Page Features
- ✅ Reservation summary display
- ✅ Total amount calculation
- ✅ GCash QR code display (placeholder)
- ✅ Drag-and-drop image upload
- ✅ Image preview before submission
- ✅ Account name input
- ✅ Amount paid input
- ✅ Payment proof validation
- ✅ Responsive design

### Security Features
- ✅ Session validation
- ✅ File type validation (images only)
- ✅ Secure file upload handling
- ✅ SQL injection prevention
- ✅ Input sanitization

## Admin Features (To Be Implemented)

The admin panel should include:
- View all pending payments
- Verify/reject payment proofs
- View payment screenshots
- Update reservation status
- Send confirmation emails

## Customization

### Change QR Code
Edit `/reservations/payment.php` around line 260:
```php
<div class="qr-placeholder">
    <!-- Replace this with your actual QR code image -->
    <img src="../uploads/gcash-qr.png" alt="GCash QR Code">
</div>
```

### Modify Payment Instructions
Edit the instruction box in `payment.php` around line 240.

### Change Upload Limits
Modify the file upload validation in `payment.php` around line 60.

## Testing

1. **Test Reservation Flow**
   - Go to `/reservations/reservation.php`
   - Fill out the form
   - Submit and verify redirect to payment page

2. **Test Payment Upload**
   - Upload a test image
   - Fill in account name and amount
   - Submit and check database

3. **Verify Database**
   - Check `payments` table for new entry
   - Check `reservations` table for status update

## Troubleshoads

### Upload Directory Not Created
- Manually create `uploads/payments/` folder
- Set permissions to 0777

### Image Not Uploading
- Check PHP `upload_max_filesize` in php.ini
- Check folder permissions
- Verify file type is allowed

### Payment Not Saving
- Check database connection
- Verify `payments` table exists
- Check error logs in `/logs/`

## Future Enhancements

- [ ] Integrate with GCash API for automatic verification
- [ ] Email notifications on payment submission
- [ ] SMS notifications
- [ ] Payment receipt generation
- [ ] Refund handling
- [ ] Multiple payment methods (PayPal, Credit Card)
- [ ] Payment history for customers
- [ ] Admin payment verification dashboard
