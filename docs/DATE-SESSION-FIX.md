# Date/session availability fix

This branch contains the date/session availability hardening for the Bubba Hub booking engine.

## Expected flow

1. The group page supplies the booking modal with the Group post ID.
2. The selected date is normalised to `YYYY-MM-DD`.
3. The availability AJAX endpoint searches published `bh_session` posts for that group/date.
4. Session metadata is normalised so legacy date/status/group formats do not silently hide valid sessions.
5. The frontend renders the returned sessions and does not retain stale results from an earlier date request.

## Test case

Demo group / St Martin's Church / 21/09/2026 / 10:00–10:30 / £5 / capacity 20 / Reserve Spot / Open.
