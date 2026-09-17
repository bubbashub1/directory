# Bubba Hub Directory — deployment ready

The repository now contains the unified directory, My Hub, leader scheduling, booking, Stripe payment and membership layers.

## Deployment

1. Deploy the complete repository to the WordPress plugin directory.
2. Activate **BubbaHub Directory**.
3. Keep the existing `group`, ACF and theme/site configuration in place.
4. Visit the Leader page once so the leader/schedule modules initialise.
5. Visit My Hub once so the customer modules initialise.
6. Confirm `/book/`, `/leader/` and `/myhub/` pages exist.

## Stripe

The Stripe integration supports organiser-connected Stripe accounts for paid class bookings.

In **WordPress → Settings → BubbaHub Payments**:

- enable Stripe;
- enter the platform secret key;
- enter the Stripe webhook signing secret;
- set GBP as the currency unless another currency is intentionally used.

The booking webhook endpoint is:

`/wp-json/bubbahub/v1/stripe/webhook`

The membership webhook endpoint is:

`/wp-json/bubbahub/v1/membership/webhook`

Leaders can connect their Stripe account through the existing Stripe Connect control.

## Booking journey

The booking stack now covers:

**Venue → class/listing → date/session → ticket type → quantity → customer details → booking record → Stripe payment → confirmed booking → My Hub**.

Free bookings can remain reserved/confirmed without a paid Stripe transaction. Paid bookings are held as a reservation while payment is pending and confirmed after the signed Stripe webhook reports payment.

## My Hub

My Hub includes:

- child profiles;
- personalised weekly planner;
- interest and preferred-location matching;
- upcoming bookings;
- previous bookings;
- ticket breakdowns;
- booking cancellation;
- payment-required cards with secure Stripe retry/payment links;
- account settings.

## Leader portal

Leader functionality includes:

- listings;
- venue management;
- recurring schedules;
- one-off sessions;
- exclusions and term-time scheduling;
- weekly leader calendar;
- attendee management;
- booking confirmation/cancellation;
- capacity tracking;
- Stripe Connect onboarding.

## Memberships

`[bubbahub_subscription]` and `[bubbahub_subscriptions]` expose Basic, Premium and Ultimate annual membership tiers. Stripe Checkout creates recurring annual subscriptions and signed membership webhooks update the user's membership status.

## Monitoring

The existing Internet Group Monitor and alert modules remain loaded through the leader bootstrap.

## Safety

Do not paste Stripe secret keys into GitHub. Configure them only in WordPress options/environment management.

The repository also contains a PHP syntax lint workflow at `.github/workflows/php-lint.yml`.
