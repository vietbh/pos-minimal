Được 👍 Mình ghi lại thành một bản **dễ đọc và lưu làm tài liệu triển khai** nhé.

# DEV → Git → PROD — Quy trình triển khai POS

composer install
symfony console tailwind:build
php bin/console cache:clear

php bin/console lint:yaml translations/messages.en.yaml
php bin/console lint:yaml translations/messages.vi.yaml

git diff --check
git status
git diff --stat



## 1. Nguyên tắc chính

```text
DEV
  ↓
Code / Test
  ↓
Git commit
  ↓
Git push
  ↓
PROD
  ↓
Deploy đúng commit
  ↓
Migration / Cache / Worker
  ↓
Smoke Test
```

**Git là Source of Truth.**

- DEV là nơi phát triển.
- Git là nơi lưu version chính thức.
- PROD chỉ nhận code từ Git.
- **Không sửa code trực tiếp trên PROD.**
- Nếu PROD có vấn đề → sửa ở DEV → commit → deploy lại.

---

# 2. Hai môi trường

### DEV

```text
~/Documents/self-projects/mobile-pos
```

Dùng để:

- code
- PHPUnit
- migration
- test business logic
- test concurrency
- test E2E
- kiểm tra UI
- commit Git

### PROD

```text
~/Documents/sites/mobile-pos/current
```

Dùng để:

- chạy ứng dụng thật
- nhận release từ Git
- chạy worker
- cron
- database production
- upload/storage production

---

# 3. Quy trình phát triển

Ở DEV:

```bash
cd ~/Documents/self-projects/mobile-pos
```

Kiểm tra:

```bash
git status
```

Chạy test:

```bash
php bin/phpunit
```

Nếu OK:

```bash
git add .
git commit -m "Implement ..."
```

Sau đó:

```bash
git push origin main
```

---

# 4. PROD không nên làm kiểu này

Không nên coi:

```bash
git pull
```

là toàn bộ quy trình production deployment.

Nó có thể chạy được với project nhỏ, nhưng POS của mình có:

- Doctrine migration
- cache
- Messenger worker
- payment
- webhook
- idempotency
- concurrency
- file upload
- background processing

nên deployment cần có các bước rõ ràng hơn.

---

# 5. Deployment cơ bản

PROD:

```bash
cd ~/Documents/sites/mobile-pos/current
```

Lấy code mới:

```bash
git fetch origin
```

Sau đó deploy đúng commit/release cần chạy.

Ví dụ:

```bash
git checkout main
git reset --hard origin/main
```

**Không nên deploy khi chưa biết chính xác commit nào đang chạy.**

Tốt hơn nữa về sau là deploy theo **exact commit SHA**.

---

# 6. Composer

Sau khi code được deploy:

```bash
composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader
```

Không chạy:

```bash
composer update
```

trên PROD.

`composer.lock` phải được commit vào Git.

---

# 7. Database migration

Nếu release có migration:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Migration phải được:

- test trước ở DEV
- review
- deploy theo release

Không tự ý sửa database production bằng tay nếu có thể giải quyết bằng migration.

---

# 8. Cache

Sau deployment:

```bash
APP_ENV=prod php bin/console cache:clear
```

Có thể warm cache:

```bash
APP_ENV=prod php bin/console cache:warmup
```

Mục tiêu là đảm bảo Symfony đang chạy đúng code/config của release mới.

---

# 9. Messenger Worker

Đây là phần **rất quan trọng đối với POS hiện tại**.

Nếu deploy code mới mà worker vẫn chạy code cũ thì có thể xảy ra:

```text
HTTP → code mới
Worker → code cũ
```

Đây là tình huống không nên có.

Sau deploy cần reload/restart worker theo process manager đang dùng.

Ví dụ nếu dùng Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart mobile-pos-worker:*
```

Nếu dùng systemd thì dùng service tương ứng.

---

# 10. Environment & Secret

Không commit production secret vào Git.

Ví dụ:

```text
.env.local
```

hoặc secret/config production phải nằm ngoài repository.

Đặc biệt:

- `DATABASE_URL`
- webhook secret
- payment credentials
- bank configuration
- API keys
- Symfony secrets

**Git chứa code/config template, không chứa secret production.**

---

# 11. Upload / Runtime Data

Không nên để dữ liệu runtime quan trọng phụ thuộc vào Git release.

Ví dụ:

```text
uploads/
var/
logs/
runtime data
```

Đặc biệt với Product Image:

```text
Product
  ↓
ProductImage
  ↓
Uploaded file
  ↓
Processing
  ↓
READY
```

File upload production phải có storage/persistence riêng.

---

# 12. Kiến trúc release tốt hơn về sau

Thay vì:

```text
current/
  code
```

có thể chuyển sang:

```text
mobile-pos/
├── releases/
│   ├── 20260921-abc123/
│   ├── 20260922-def456/
│   └── ...
│
├── shared/
│   ├── .env.local
│   ├── uploads/
│   └── ...
│
└── current -> releases/20260922-def456/
```

Khi deploy:

```text
Git commit
    ↓
new release
    ↓
composer install
    ↓
migration
    ↓
cache warmup
    ↓
switch current
    ↓
restart/reload worker
    ↓
smoke test
```

Ưu điểm lớn nhất là **rollback dễ hơn**.

Ví dụ release mới lỗi:

```text
current
   ↓
release A  ← lỗi
```

có thể chuyển lại:

```text
current
   ↓
release trước
```

thay vì phải sửa chữa trực tiếp trong thư mục production.

---

# 13. Smoke Test sau deployment

Không coi deployment thành công chỉ vì:

```text
composer install → OK
migration → OK
```

Cần kiểm tra application thật.

Tối thiểu:

```text
Login
  ↓
Application Shell
  ↓
POS
  ↓
Create/Start checkout
  ↓
Payment
  ↓
Complete sale
  ↓
Order
  ↓
Stock
```

Với Bank Transfer:

```text
Start payment
  ↓
PaymentReference
  ↓
QR/reference
  ↓
Webhook
  ↓
Payment
  ↓
Complete sale
  ↓
Order completed
```

Và kiểm tra DB state tương ứng.

---

# 14. Production không được sửa tay

Nếu phát hiện:

```text
BUG
```

Không nên:

```text
ssh PROD
→ sửa PHP
→ chạy thử
→ quên commit
```

Vì lúc đó:

```text
Git ≠ PROD
```

và lần deploy tiếp theo code sửa tay sẽ biến mất.

Đúng quy trình:

```text
PROD BUG
   ↓
DEV
   ↓
Fix
   ↓
Test
   ↓
Commit
   ↓
Push
   ↓
Deploy
```

---

# 15. Quy tắc vàng

Có thể lưu nguyên block này làm **Deployment Rules**:

```text
1. Git là Source of Truth.

2. DEV là nơi phát triển và sửa code.

3. PROD không sửa code trực tiếp.

4. Mọi production change phải đi qua Git.

5. Không chạy composer update trên PROD.

6. composer.lock phải được version control.

7. Migration phải được test trước khi chạy PROD.

8. Production secrets không commit vào Git.

9. Upload/runtime data phải persistent ngoài release.

10. Worker phải được restart/reload sau deployment
    khi code worker thay đổi.

11. Deployment phải xác định exact release/commit.

12. Sau deployment phải chạy smoke test.

13. Nếu deployment lỗi:
    stop → xác định release → rollback/fix,
    không sửa lung tung trực tiếp trên PROD.

14. Về lâu dài:
    releases/ + shared/ + current symlink.

15. Mục tiêu:
    DEV → Git → Release → PROD
    và có thể rollback.
```

### Tóm tắt một dòng

> **Code ở DEV → test → commit → push Git → deploy exact release → migration → cache → worker → smoke test → PROD.**

Đây là flow mình khuyên giữ làm **chuẩn triển khai chính thức cho Mobile POS** từ giai đoạn này trở đi.
