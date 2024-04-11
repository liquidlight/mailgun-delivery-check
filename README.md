# Mailgun Verification

Searches recent emails sent by Mailgun for a predefined string.

Used for testing purposes - for example, filling a contact form in and ensuring the email was sent

## Usage

There are 3 environment variables needed to run, with 1 optional

- `TEXT_TO_FIND` - The text to look for in the email (uses PHP's `str_contains`)
- `MAILGUN_API_KEY` - Your API Key
- `MAILGUN_DOMAIN` - The domain set in Mailgun
- `MAILGUN_IS_EU` - si your Mailgun region EU?

## Running Locally

You can run locally with the following:

```
docker run --env TEXT_TO_FIND='text to find' --MAILGUN_API_KEY='[API KEY]' --MAILGUN_DOMAIN='domain' ghcr.io/liquidlight/mailgun-delivery-check
```
