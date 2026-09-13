# BubbaHub Booking Engine — Stage 1

## What this stage does

This stage creates the stable booking data layer without changing the existing Group directory or Group page code.

It adds two private WordPress post types:

- **Booking Sessions (`bh_session`)** — an individual date/time occurrence of a Group.
- **Bookings (`bh_booking`)** — a customer's reservation against a session.

The engine also provides a secure AJAX availability endpoint for the future Ninja Forms date/session selector.

## Session fields

The session layer is deliberately independent from Ninja Forms and GetPaid.

| Field | Meta key | Purpose |
|---|---|---|
| Group | `_bh_group_id` | The Group/class being booked |
| Venue | `_bh_venue_id` | Venue post used by this session |
| Date | `_bh_date` | `Y-m-d` session date |
| Start | `_bh_start_time` | Start time |
| End | `_bh_end_time` | End time |
| Capacity | `_bh_capacity` | Maximum places; `0` means unlimited |
| Session status | `_bh_session_status` | `open` or another closed value |
| Sort date/time | `_bh_datetime_sort` | Used for chronological availability results |
| Price | `_bh_price` | Price for the session |
| Booking method | `_bh_booking_method` | `form`, `reserve`, `external`, or future values |
| External URL | `_bh_external_url` | External booking destination when applicable |
| Reserve enabled | `_bh_reserve_enabled` | Whether reserve mode is available |

## Booking fields

| Field | Meta key |
|---|---|
| Session | `_bh_session_id` |
| Group | `_bh_group_id` |
| Venue | `_bh_venue_id` |
| WordPress user | `_bh_user_id` |
| Customer name | `_bh_customer_name` |
| Customer email | `_bh_customer_email` |
| Places | `_bh_places` |
| Booking status | `_bh_status` |
| Payment status | `_bh_payment_status` |
| Payment method | `_bh_payment_method` |
| GetPaid invoice | `_bh_invoice_id` |
| Notes | `_bh_notes` |

Confirmed and reserved bookings count against capacity. Failed/cancelled/pending records do not.

## Planned next stages

### Stage 2 — ACF Pro + session management

Add the ACF Pro field group and organiser-friendly session editor. This will make it practical to create dates, times, capacities, prices, booking method and external URLs from WordPress rather than manually editing meta.

### Stage 3 — Ninja Forms

Connect the Group page booking area to Ninja Forms:

1. Select venue.
2. Select class/group.
3. Select available date.
4. Select available session/time.
5. Show remaining places.
6. Collect parent/customer details.
7. Choose **Book Now**, **Reserve Spot**, or **External Booking** where enabled.

### Stage 4 — GetPaid

Create the GetPaid invoice/payment only after the booking/session validation succeeds. The booking record will retain the GetPaid invoice ID and payment status.

GetPaid supports payment forms, invoices and multiple gateways, and its Ninja Forms integration can create invoices from submitted forms. The custom BubbaHub layer remains responsible for session availability and capacity.

### Stage 5 — Leader dashboard + wallet

Add organiser visibility, booking management, refunds/cancellations, payout accounting and the later leader wallet rules.

## Important stability rule

Do **not** modify the existing `bubbahub-directory.php`, `directory.js` or `directory.css` in Stage 1. The booking engine is isolated so it can be activated and tested independently before the Group page booking button is connected.
