Được. Mình chốt thành **1 quy trình cực đơn giản**, mỗi bước đều có điểm kiểm tra. Nếu lỗi thì **dừng ngay tại bước đó**, không chạy tiếp.

# 🚀 DEV → PROD — Quy trình chuẩn

## A. DEV — chuẩn bị release

```bash
cd ~/Documents/self-projects/mobile-pos

git status --short
git diff --check
php bin/phpunit
git log -1 --oneline
```

Phải thấy:

```text
c23a717 feat: harden manual bank transfer completion
```

Nếu test lỗi → **DỪNG**, sửa DEV rồi test lại.

Nếu OK:

```bash
git push origin main
```

---

# B. PROD — chuẩn bị đúng version

```bash
cd ~/Documents/sites/mobile-pos/repo

git fetch origin
git checkout c23a717

git log -1 --oneline
```

Phải là:

```text
c23a717
```

Nếu không phải → **DỪNG**.

---

# C. PROD — kiểm tra/build trước khi thay current

```bash
composer install --no-dev --optimize-autoloader
```

Sau đó:

```bash
APP_ENV=prod php bin/console about
```

Kiểm tra migration:

```bash
APP_ENV=prod php bin/console doctrine:migrations:status
```

Nếu có migration mới:

```bash
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
```

Build asset:

```bash
APP_ENV=prod php bin/console tailwind:build
APP_ENV=prod php bin/console asset-map:compile
```

### Nếu bất kỳ lệnh nào lỗi

👉 **DỪNG.**

Chưa đụng `current`.

---

# D. Backup `current`

```bash
cd ~/Documents/sites/mobile-pos

BACKUP="current.backup-$(date +%Y%m%d-%H%M%S)"

mv current "$BACKUP"
mkdir current
```

Kiểm tra:

```bash
ls -ld "$BACKUP"
ls -la "$BACKUP/.env"
```

Phải thấy backup và `.env`.

Nếu không thấy → **DỪNG**.

---

# E. Copy release vào `current`

```bash
rsync -a --delete \
  --exclude='.env' \
  --exclude='var/' \
  repo/ current/
```

Kiểm tra:

```bash
ls -la current/bin/console
```

Phải tồn tại.

Nếu không có → **DỪNG**.

---

# F. Khôi phục `.env` + `var`

```bash
cp "$BACKUP/.env" current/.env
cp -a "$BACKUP/var" current/var
```

Kiểm tra:

```bash
ls -la current/.env
ls -ld current/var
```

Nếu thiếu `.env` hoặc `var` → **DỪNG**.

---

# G. Permission

```bash
cd ~/Documents/sites/mobile-pos/current

sudo chown -R vietbh:www-data var
sudo chmod -R ug+rwX var
```

Sau đó:

```bash
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console cache:warmup
```

Nếu cache lỗi → **DỪNG**.

**Không reload PHP-FPM khi cache chưa PASS.**

---

# H. Reload PHP-FPM

Chỉ khi cache PASS:

```bash
sudo systemctl reload php8.2-fpm
```

---

# I. Smoke test

```bash
curl -I https://pos.mini-store-app.io.vn/
```

Sau đó:

```bash
curl -I https://pos.mini-store-app.io.vn/auth/login
```

Kiểm tra log:

```bash
tail -n 50 var/log/prod.log
```

Kỳ vọng:

```text
/                  → 302
/auth/login        → 200
```

Không có lỗi production mới.

---

# J. PASS → xóa backup

**Chỉ xóa sau khi browser test OK.**

```bash
cd ~/Documents/sites/mobile-pos

ls -ld current.backup-*
```

Sau đó:

```bash
rm -rf current.backup-20260921-043015
```

---

# 🔴 Nếu PROD bị lỗi thì làm gì?

Quy tắc rất đơn giản:

> **Lỗi ở bước nào → dừng ở bước đó. Không chạy tiếp.**

Nếu lỗi **trước bước D**:

```text
current vẫn nguyên vẹn
→ sửa lỗi
→ chạy lại
```

Nếu lỗi **sau bước D**:

```text
current mới bị lỗi
        ↓
backup vẫn còn
        ↓
rollback
```

Rollback:

```bash
cd ~/Documents/sites/mobile-pos

rm -rf current
mv current.backup-20260921-043015 current
```

Sau đó:

```bash
cd current

sudo chown -R vietbh:www-data var
sudo chmod -R ug+rwX var

APP_ENV=prod php bin/console cache:clear
sudo systemctl reload php8.2-fpm
```

Test lại:

```bash
curl -I https://pos.mini-store-app.io.vn/auth/login
```

---

# 🧠 Nhớ 5 nguyên tắc

```text
1. DEV phải PASS test
        ↓
2. Git phải có đúng SHA
        ↓
3. PROD/repo checkout đúng SHA
        ↓
4. Backup current trước khi thay
        ↓
5. Smoke test PASS → mới xóa backup
```

Và quan trọng nhất:

```text
❌ Không git pull trong current
❌ Không sửa code trực tiếp trong current
❌ Không xóa backup trước khi smoke test
❌ Không xóa .env
❌ Không xóa var/
❌ Không chown var thành www-data:www-data
```

**Trường hợp hiện tại của bạn:** đã hoàn thành bước **D**, backup là:

```text
current.backup-20260921-043015
```

và `current/` đang rỗng.

👉 Vì vậy **bước tiếp theo duy nhất là E — `rsync repo/ → current/`**.
