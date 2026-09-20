# DEV → Git → PROD Deployment / Pull Code Runbook

## 1. Nguyên tắc

- DEV là nơi sửa code.
- GitHub là source of truth.
- PROD không sửa code trực tiếp.
- PROD deploy theo **exact commit SHA**, không dùng `git pull` mù vào `current`.
- `.env`, `var/`, upload/storage/runtime data không commit vào Git.
- Sau mỗi release phải kiểm tra Composer, migration, cache và smoke test.

---

## 2. DEV — kiểm tra trước khi commit

```bash
cd ~/Documents/self-projects/mobile-pos

git status --short
git diff --check
php bin/phpunit
```

Nếu có thay đổi AssetMapper/Tailwind và cần build frontend:

```bash
php bin/console tailwind:build
```

Sau đó kiểm tra lại source/assets theo workflow của project.

Nếu cần rebuild AssetMapper:

```bash
rm -rf public/assets
php bin/console asset-map:compile
```

Sau đó clear cache:

```bash
APP_ENV=prod php bin/console cache:clear
```

> Nếu project hiện tại dùng command khác cho bước asset compile, giữ đúng command đang được project sử dụng. Không xóa `assets/` source directory; chỉ xóa generated output theo đúng workflow.

---

## 3. DEV — stage và commit

```bash
git status --short
git add -A
git diff --cached --check
git diff --cached --stat
```

Nếu staged batch đúng:

```bash
git commit -m "feat: <release description>"
```

Kiểm tra:

```bash
git status --short
git log -1 --oneline
```

Working tree phải sạch.

---

## 4. DEV — push GitHub

```bash
git push origin main
```

Lấy exact SHA:

```bash
git log -1 --oneline
```

Ví dụ:

```text
828c7de feat: consolidate POS workflow and payment updates
```

---

# 5. PROD — cập nhật Git repository

PROD hiện dùng:

```text
~/Documents/sites/mobile-pos/
├── current/    # live application
├── repo/       # Git checkout
└── shared/     # runtime/shared data
```

Không chạy `git pull` trực tiếp vào `current`.

Vào repo:

```bash
cd ~/Documents/sites/mobile-pos/repo

git status --short
git fetch origin
git checkout <EXACT_SHA>

git log -1 --oneline
git status --short
```

Phải đúng SHA vừa push từ DEV.

---

# 6. PROD — Composer

```bash
composer install --no-dev --optimize-autoloader
```

Kỳ vọng:

```text
cache:clear [OK]
assets:install [OK]
importmap:install [OK]
```

Sau đó:

```bash
APP_ENV=prod php bin/console about
```

Kiểm tra:

- Environment = `prod`
- Debug = `false`
- PHP version đúng
- Symfony boot thành công

---

# 7. PROD — Database migration

Kiểm tra trước:

```bash
APP_ENV=prod php bin/console doctrine:migrations:status
```

Chạy:

```bash
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
```

Nếu:

```text
Already at the latest version
```

thì không có migration mới cần chạy.

Không tự ý xóa/sửa các migration cũ chỉ vì Doctrine báo:

```text
Executed Unavailable
```

Đó là lịch sử migration đã từng chạy nhưng không còn nằm trong source hiện tại.

---

# 8. PROD — activate release

Production hiện tại sử dụng:

```text
/home/vietbh/Documents/sites/mobile-pos/current/public
```

Khi deploy release mới:

1. Backup `current`.
2. Copy source từ `repo` exact SHA sang `current`.
3. Không overwrite `.env`.
4. Không overwrite `var/`.
5. Giữ upload/storage/runtime data.

Ví dụ:

```bash
cd ~/Documents/sites/mobile-pos

BACKUP="current.backup-$(date +%Y%m%d-%H%M%S)"
mv current "$BACKUP"

mkdir current

rsync -a --delete \
  --exclude='.env' \
  --exclude='var/' \
  repo/ current/

cp "$BACKUP/.env" current/.env
cp -a "$BACKUP/var" current/var
```

---

# 9. PROD — runtime permissions

PHP-FPM chạy bằng `www-data`, còn CLI deploy chạy bằng `vietbh`.

Giữ `var/` theo group `www-data` và cho owner/group cùng quyền ghi:

```bash
cd ~/Documents/sites/mobile-pos/current

sudo chown -R vietbh:www-data var
sudo chmod -R ug+rwX var
```

Đặc biệt kiểm tra:

```bash
ls -ld var var/cache var/cache/prod var/log
```

---

# 10. PROD — cache

Sau khi source đã được activate:

```bash
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console cache:warmup
```

Nếu dùng AssetMapper/Tailwind trong release hiện tại, đảm bảo generated assets đã được build/compile đúng trước khi smoke test.

---

# 11. PROD — PHP-FPM

Sau khi release và cache PASS:

```bash
sudo systemctl reload php8.2-fpm
```

---

# 12. PROD — smoke test

Kiểm tra root:

```bash
curl -I https://pos.mini-store-app.io.vn/
```

Kỳ vọng:

```text
HTTP/2 302
location: /auth/login
```

Kiểm tra login:

```bash
curl -sk -D /tmp/login.headers \
  -o /tmp/login.html \
  https://pos.mini-store-app.io.vn/auth/login

head -20 /tmp/login.headers
```

Kỳ vọng:

```text
HTTP/2 200
```

Nếu lỗi 500, xem production log:

```bash
tail -n 100 current/var/log/prod.log
```

Không đoán lỗi từ Cloudflare/Nginx trước khi xem Symfony log.

---

# 13. Sau deploy — browser smoke test

Tối thiểu kiểm tra:

1. `/auth/login`
2. Đăng nhập
3. Application/Home shell
4. POS
5. Product
6. Customer
7. Order
8. Payment/Bank Transfer nếu release có thay đổi
9. Logout

Chỉ xóa `current.backup-*` sau khi browser smoke test PASS.

---

# 14. Rollback

Nếu release mới lỗi:

```bash
cd ~/Documents/sites/mobile-pos

mv current current.failed-$(date +%Y%m%d-%H%M%S)
mv current.backup-<TIMESTAMP> current
```

Sau đó:

```bash
sudo systemctl reload php8.2-fpm
```

Smoke test lại:

```bash
curl -IL --max-redirs 5 https://pos.mini-store-app.io.vn/
```

---

# 15. Quy trình ngắn gọn hằng ngày

## DEV

```bash
cd ~/Documents/self-projects/mobile-pos

git status --short
git diff --check
php bin/phpunit

git add -A
git diff --cached --check
git commit -m "feat: <description>"
git push origin main

git log -1 --oneline
```

## PROD

```bash
cd ~/Documents/sites/mobile-pos/repo

git fetch origin
git checkout <EXACT_SHA>

composer install --no-dev --optimize-autoloader

APP_ENV=prod php bin/console doctrine:migrations:status
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction

APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console cache:warmup
```

Sau đó activate source vào `current`, giữ `.env` + `var/`, sửa permission nếu cần, reload PHP-FPM và smoke test.

---

# 16. Asset/Tailwind — lưu ý từ release hiện tại

Nếu sau khi pull release mới mà generated frontend assets chưa đúng, workflow hiện tại có thể cần:

```bash
php bin/console tailwind:build
```

sau đó xóa **generated asset output**, rồi:

```bash
php bin/console asset-map:compile
APP_ENV=prod php bin/console cache:clear
```

**Không xóa `assets/` source directory.**

Chỉ xóa thư mục/file generated output mà project thực sự sử dụng.

---

# 17. Release hiện tại đã xác nhận

Release workflow vừa hoàn thành:

```text
DEV
  ↓
828c7de
  ↓
GitHub
  ↓
PROD repo
  ↓
2a4ef33 (logging follow-up)
  ↓
Composer
  ↓
Migration
  ↓
Cache
  ↓
PHP-FPM
  ↓
HTTPS
```

Lỗi HTTP 500 đã được xác định là:

```text
var/cache/prod/asset_mapper
mkdir(): Permission denied
```

Nguyên nhân là permission của `var/cache` không cho PHP-FPM `www-data` ghi.

Fix:

```bash
sudo chown -R vietbh:www-data var
sudo chmod -R ug+rwX var
```

Production Monolog đã được đổi để ghi vào:

```text
var/log/prod.log
```

thay vì chỉ `php://stderr`, giúp debug HTTP 500 trực tiếp trên application log.
