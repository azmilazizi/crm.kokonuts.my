# Affiliate module

## Install on cPanel

1. Deploy the `modules/affiliate` directory and the added `application/config/routes.php` entries.
2. Log in as an administrator and open **Setup > Modules**.
3. Activate **Affiliate**. Activation creates the affiliate product, category, and click tables.
4. Grant staff the `Affiliate Products` permissions under **Setup > Roles**.
5. Open **Affiliate > Products** and add a product.

The public endpoints are:

- `GET /affiliate/public/items`
- `GET /affiliate/go/{item_no}`

The public JSON returns CRM redirect URLs for the Shopee/TikTok links so clicks can be counted. It does not expose private notes or original platform URLs.

## Manual test checklist

- Add a Shopee-only product and confirm it receives the next permanent item number.
- Add a TikTok-only product and confirm its expiry defaults to roughly 100 days.
- Add a product with both links and confirm both redirect choices work.
- Set a product hidden and confirm it disappears from `/affiliate/public/items` and redirects to `https://affiliate.kokonuts.my/?q={item_no}`.
- Let/force a TikTok expiry date into the past and confirm the TikTok link disappears while a Shopee link remains.
- Open the public JSON without logging in and confirm it contains only `items` and `categories`.
- Open the short links and confirm the Click Analytics screen records the platform and item number.
- Test the 7-day expiry filter and CSV export.
- Test a staff role without Affiliate permission and confirm the menu/page is inaccessible.
