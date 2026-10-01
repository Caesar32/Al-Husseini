# 🚀 دليل إعداد وتشغيل نظام النشر التلقائي (CI/CD Production Deployment Guide)
### Al-Husseini Management System | GitHub Actions to Production Server

---

## 📌 1. نظرة عامة على المعمارية (Architecture & Workflow)

تم تصميم خط أنابيب التكامل والنشر المستمر (**CI/CD Pipeline**) وفق أعلى معايير أمان وكفاءة الإنتاج (**Zero-Load Production Build**):

```mermaid
flowchart LR
    Dev[Developer Push to main] --> CI[GitHub Runner: CI Pipeline]
    CI -->|Tests & Lint Pass| CD[GitHub Runner: CD Deployment]
    
    subgraph GitHubRunner["بيئة بناء خفيفة (GitHub Runner)"]
        CD -->|1. Composer install --no-dev| Vendor[Vendor الإنتاج]
        CD -->|2. NPM ci & build| Assets[أصول Vite المبنية]
        CD -->|3. حزمة نظيفة مستثنى منها الاختبارات| Tar[release.tar.gz]
    end

    subgraph ProductionServer["خادم الإنتاج (Production Server)"]
        Tar -->|4. رفع عبر SCP| UploadDir[/.deploy_upload/]
        UploadDir -->|5. استخراج إلى مجلد مؤقت| Staging[/.deploy_tmp/]
        Staging -->|6. وضع الصيانة down| Maintenance[حجب مؤقت للطلبات]
        Maintenance -->|7. مزامنة ذكية rsync| LiveApp[/var/www/alhusseini]
        LiveApp -->|8. ترحيل الداتابيز وتجديد الكاش| Cache[Rebuild Caches]
        Cache -->|9. إعادة الموقع للعمل وفحص الصحة| Up[php artisan up & Health Check]
    end
```

### 💡 مميزات المعمارية المطبقة:
1. **توفير موارد السيرفر بالكامل:** بناء حزم الـ `npm run build` والـ `composer install` يتم داخل خوادم GitHub المجانية، فلا يستهلك السيرفر أي RAM أو CPU لعمليات الـ Compile.
2. **الحفاظ التام على البيانات:** استثناء دائم لملف `.env`، مجلد `storage/`، ومجلد الميديا والمرفقات `public/uploads/`، وملفات قواعد البيانات `database/*.sqlite*`.
3. **أمان عند حدوث أخطاء مفاجئة:** وجود `trap cleanup EXIT` لحذف الملفات المؤقتة تلقائياً وعدم ترك مخلفات في السيرفر سواء نجحت العملية أو فشلت.

---

## 🔑 2. مفاتيح وإعدادات الأمان في GitHub (Repository Secrets)

يجب إضافة المتغيرات السرية التالية في مستودع GitHub عبر:  
`Settings` -> `Secrets and variables` -> `Actions` -> `New repository secret`

| اسم الـ Secret في GitHub | الوصف ومثال القيمة |
|:---|:---|
| `SSH_HOST` | الآي بي الخاص بالسيرفر أو الدومين (مثال: `198.51.100.24` أو `server.alhusseini.com`) |
| `SSH_USER` | اسم المستخدم المخصص للنشر على السيرفر (مثال: `deployer` أو `ubuntu`) |
| `SSH_PRIVATE_KEY` | **المفتاح الخاص (Private Key)** كاملاً الذي تم توليده للربط مع السيرفر (يبدأ بـ `-----BEGIN OPENSSH PRIVATE KEY-----`) |
| `SSH_PORT` | منفذ الـ SSH على السيرفر (الافتراضي: `22`) |
| `REMOTE_TARGET_PATH` | المسار المطلق لمجلد المشروع على السيرفر (مثال: `/var/www/alhusseini`) |

---

## 🛠️ 3. خطوات تجهيز السيرفر والـ SSH خطوة بخطوة

### الخطوة 1: توليد زوج مفاتيح SSH مخصص للـ Deploy
من جهازك المحلي أو من داخل السيرفر، نفذ الأمر التالي:
```bash
ssh-keygen -t ed25519 -C "github-actions-deploy@alhusseini" -f ~/.ssh/github_deploy
```
* **المفتاح الخاص (`github_deploy`):** انسخ محتواه بالكامل وضعه في GitHub Secret باسم `SSH_PRIVATE_KEY`.
* **المفتاح العام (`github_deploy.pub`):** انسخ محتواه وضعه داخل ملف المفاتيح المصرح لها في السيرفر.

### الخطوة 2: إضافة المفتاح العام في السيرفر
سجل الدخول للسيرفر، ثم أضف المفتاح إلى `authorized_keys`:
```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
cat >> ~/.ssh/authorized_keys << 'EOF'
# الصق هنا محتوى github_deploy.pub
EOF
chmod 600 ~/.ssh/authorized_keys
```

### الخطوة 3: إنشاء مسار المشروع وضبط الصلاحيات
```bash
# إنشاء مجلد المشروع
sudo mkdir -p /var/www/alhusseini
sudo chown -R $USER:www-data /var/www/alhusseini

# إنشاء المجلدات الأساسية الدائمة
mkdir -p /var/www/alhusseini/storage/app/public
mkdir -p /var/www/alhusseini/storage/framework/cache/data
mkdir -p /var/www/alhusseini/storage/framework/sessions
mkdir -p /var/www/alhusseini/storage/framework/views
mkdir -p /var/www/alhusseini/storage/logs
mkdir -p /var/www/alhusseini/public/uploads/avatars
mkdir -p /var/www/alhusseini/bootstrap/cache
mkdir -p /var/www/alhusseini/.deploy_upload

# ضبط الصلاحيات القياسية لـ Laravel
sudo chmod -R 775 /var/www/alhusseini/storage /var/www/alhusseini/bootstrap/cache /var/www/alhusseini/public/uploads
```

---

## ⚙️ 4. ملف البيئة الأولي على السيرفر (`.env`)

يجب إنشاء ملف `.env` يدوياً على السيرفر لمرة واحدة فقط داخل مسار المشروع `/var/www/alhusseini/.env`:

```ini
APP_NAME="Al-Husseini Management"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://alhusseini.com

LOG_CHANNEL=daily
LOG_LEVEL=error

# إعدادات قاعدة البيانات (MySQL أو SQLite بحسب خادمك)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=alhusseini_prod
DB_USERNAME=alhusseini_user
DB_PASSWORD="SECURE_DATABASE_PASSWORD"

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

FILESYSTEM_DISK=local
```

> [!NOTE]
> قم بتوليد مفتاح التطبيق عبر تشغيل: `php artisan key:generate` داخل السيرفر إذا لم يكن متوفراً مسبقاً.

---

## 🌐 5. إعداد خادم الويب (Nginx Web Server Configuration)

تأكد من توجيه `root` في إعدادات Nginx إلى مجلد `public` الخاص بالمشروع:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name alhusseini.com www.alhusseini.com;
    root /var/www/alhusseini/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    # ملفات رفعها المستخدمون سابقاً داخل المجلد العام (الصور الشخصية القديمة): تُخدَّم كصور فقط ولا تُنفَّذ
    # الصور الجديدة تُحفظ خارج public وتُعرض عبر مسار مُصادَق عليه (admin/profile/avatar/{user})
    location ^~ /uploads/ {
        location ~* \.(php|phtml|phar|pht|html?|svg|shtml)$ { deny all; }
        add_header X-Content-Type-Options "nosniff";
    }

    # مسار فحص الصحة المستخدم في الـ CI/CD
    location = /up {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # معالجة رفع الملفات والميديا
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## ⏱️ 6. إعداد المهام المجدولة وطابور العمليات (Cron & Supervisor)

### أ. مهام لارافيل المجدولة (Cron Job):
أضف السطر التالي إلى كرون السيرفر عبر `crontab -e`:
```bash
* * * * * cd /var/www/alhusseini && php artisan schedule:run >> /dev/null 2>&1
```

### ب. تشغيل الـ Queue Worker عبر Supervisor (اختياري للإشعارات والمهام الخلفية):
أنشئ ملف إعدادات `/etc/supervisor/conf.d/alhusseini-worker.conf`:
```ini
[program:alhusseini-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/alhusseini/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/alhusseini/storage/logs/worker.log
stopwaitsecs=3600
```
ثم قم بتفعيله:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start alhusseini-worker:*
```

---

## 🚀 7. تشغيل واختبار الـ Pipeline

### أ. النشر التلقائي (Automatic Deploy):
بمجرد عمل `git push origin main`، سيحدث الآتي تلقائياً:
1. ينطلق سير عمل الـ **`CI Pipeline`** لتشغيل الاختبارات وفحص الكود وبناء أصول Vite والتحقق من الكاش.
2. بمجرد نجاح الـ CI، ينطلق تلقائياً سير عمل الـ **`Deploy to Production`**.
3. يتم إرسال الباقة للسيرفر، تفعيل وضع الصيانة، مزامنة الملفات، تشغيل الـ Migrations، تجديد الكاش، وإعادة الموقع للعمل مع فحص الصحة `Health check`.

### ب. النشر اليدوي (Manual Dispatch):
يمكنك في أي وقت نشر أي تحديث يدوياً من واجهة GitHub:
1. اذهب لتبويب **Actions** في مستودع GitHub.
2. اختر **`Deploy to Production`**.
3. اضغط على **Run workflow**.
4. حدد ما إذا كنت تريد تشغيل الـ `migrate --force` (مفعل افتراضياً) ثم اضغط تأكيد.
