# Product Create Persistence Fix

The product form had already been updated to support optional categories and server-generated unique SKUs, but the database migrations did not yet contain the `product_categories` table or the nullable `products.category_id` relation required by the current Doctrine entity mappings.

This phase adds migration `Version20260919235900` to persist that schema consistently for fresh and existing installations.

After deployment:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
```

The successful POST path remains a redirect to the product detail page. Validation/domain failures return HTTP 422 for Turbo compatibility.
