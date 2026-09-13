#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

replacements = {
    # Controllers: route attribute path only.
    'src/Controller/Order/CheckoutController.php': {
        "#[Route('/pos', name: 'pos'": "#[Route('/app/pos', name: 'pos'",
        "#[Route('/api/pos/checkout', name: 'pos_checkout'": "#[Route('/app/checkout', name: 'pos_checkout'",
    },
    'src/Controller/Order/OrderController.php': {
        "#[Route('/orders', name: 'orders_index'": "#[Route('/app/orders', name: 'orders_index'",
        "#[Route('/orders/{id}', name: 'orders_show'": "#[Route('/app/orders/{id}', name: 'orders_show'",
    },
    'src/Controller/Order/OrderLifecycleController.php': {
        "#[Route('/api/orders/{id}/cancel', name: 'order_cancel'": "#[Route('/app/orders/{id}/cancel', name: 'order_cancel'",
        "#[Route('/api/orders/{id}/refund', name: 'order_refund'": "#[Route('/app/orders/{id}/refund', name: 'order_refund'",
    },
    'src/Controller/Product/ProductImageController.php': {
        "#[IsGranted('ROLE_USER')]": "#[IsGranted('ROLE_ADMIN')]",
        "#[Route('/products/{productId}/images/upload', name: 'product_image_upload_form'": "#[Route('/admin/products/{productId}/images/upload', name: 'product_image_upload_form'",
        "#[Route('/products/{productId}/images', name: 'product_image_upload'": "#[Route('/admin/products/{productId}/images', name: 'product_image_upload'",
        "#[Route('/product-images/{id}/{variant}', name: 'product_image_serve'": "#[Route('/media/products/{id}/{variant}', name: 'product_image_serve'",
        "#[Route('/product-images/{id}', name: 'product_image_delete'": "#[Route('/media/products/{id}', name: 'product_image_delete'",
    },
    'src/Controller/SecurityController.php': {
        "#[Route('/login', name: 'login'": "#[Route('/auth/login', name: 'login'",
        "#[Route('/logout', name: 'logout'": "#[Route('/auth/logout', name: 'logout'",
    },
}

for rel, mapping in replacements.items():
    path = ROOT / rel
    if not path.exists():
        print(f'SKIP {rel} (not present)')
        continue
    text = path.read_text()
    original = text
    for old, new in mapping.items():
        text = text.replace(old, new)
    if text != original:
        path.write_text(text)
        print(f'UPDATED {rel}')
    else:
        print(f'NOCHANGE {rel}')

security = ROOT / 'config/packages/security.yaml'
if security.exists():
    text = security.read_text()
    original = text
    text = text.replace('- { path: ^/login$, roles: PUBLIC_ACCESS }', '- { path: ^/auth/login$, roles: PUBLIC_ACCESS }')
    if text != original:
        security.write_text(text)
        print('UPDATED config/packages/security.yaml')
    else:
        print('NOCHANGE config/packages/security.yaml')

# Update test literals without touching unrelated filesystem paths.
for rel in [
    'tests/Integration/Http/CheckoutControllerTest.php',
    'tests/Integration/Security/AuthenticationTest.php',
]:
    path = ROOT / rel
    if not path.exists():
        print(f'SKIP {rel} (not present)')
        continue
    text = path.read_text()
    original = text
    text = text.replace("'/api/pos/checkout'", "'/app/checkout'")
    text = text.replace("'/pos'", "'/app/pos'")
    text = text.replace("'/login'", "'/auth/login'")
    if text != original:
        path.write_text(text)
        print(f'UPDATED {rel}')
    else:
        print(f'NOCHANGE {rel}')

print('Route namespace migration complete.')
