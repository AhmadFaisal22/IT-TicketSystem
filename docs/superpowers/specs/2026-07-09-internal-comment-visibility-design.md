# Internal Comment Visibility Fix

## Goal

Prevent non-IT users who may view a ticket from receiving comments marked
`is_internal=true` in the ticket detail API response.

## Design

Keep the existing `GET /api/tickets/{ticket}` response shape. When loading the
ticket's `comments` relationship, apply an `is_internal=false` constraint for
non-IT users. Admin and IT staff continue to receive all comments. The separate
comments endpoint remains unchanged because it already enforces the same rule.

## Tests

Add feature coverage proving that:

- A ticket creator receives public comments but not internal comments.
- IT staff receive both public and internal comments.

The regression test must fail before the controller change and pass afterward.
