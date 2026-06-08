#!/bin/bash
# ============================================================
# IT HelpDesk — One-time server setup script
# Run as root on a fresh Ubuntu 22.04 VPS
# ============================================================
set -e

DOMAIN="helpdesk.yourdomain.com"       # <-- change this
DB_NAME="it_helpdesk"
DB_USER="helpdesk"
DB_PASS="change_this_strong_password"  # <-- change this
DEPLOY_USER="deploy"

echo "======================================"
echo " IT HelpDesk — Server Setup"
echo "======================================"

# ── 1. System updates ─────────────────────────────────────
apt update && apt upgrade -y
apt install -y curl git unzip software-properties-common ufw fail2ban

# ── 2. PHP 8.2 ────────────────────────────────────────────
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2 php8.2-fpm php8.2-pgsql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl \
  php8.2-redis php8.2-gd

# ── 3. Composer ───────────────────────────────────────────
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# ── 4. Node.js 20 ─────────────────────────────────────────
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# ── 5. PostgreSQL ──────────────────────────────────────────
apt install -y postgresql postgresql-contrib
systemctl enable postgresql

sudo -u postgres psql <<SQL
CREATE DATABASE $DB_NAME;
CREATE USER $DB_USER WITH ENCRYPTED PASSWORD '$DB_PASS';
GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
ALTER DATABASE $DB_NAME OWNER TO $DB_USER;
SQL

# ── 6. Nginx ──────────────────────────────────────────────
apt install -y nginx
systemctl enable nginx

cat > /etc/nginx/sites-available/it-helpdesk <<NGINX
server {
    listen 80;
    server_name $DOMAIN;

    # Frontend (Vue SPA)
    root /var/www/it-helpdesk/it-helpdesk-frontend/dist;
    index index.html;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    # Backend API + storage
    location ~ ^/(api|storage)/ {
        root /var/www/it-helpdesk/it-helpdesk-backend/public;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME /var/www/it-helpdesk/it-helpdesk-backend/public/index.php;
        include fastcgi_params;
        fastcgi_param HTTP_HOST \$host;
    }

    # Laravel public fallback (handles /storage links correctly)
    location /storage {
        alias /var/www/it-helpdesk/it-helpdesk-backend/storage/app/public;
    }

    client_max_body_size 20M;
}
NGINX

ln -sf /etc/nginx/sites-available/it-helpdesk /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ── 7. SSL via Let's Encrypt ───────────────────────────────
apt install -y certbot python3-certbot-nginx
certbot --nginx -d $DOMAIN --non-interactive --agree-tos -m admin@yourdomain.com
systemctl enable certbot.timer

# ── 8. Deploy user (for GitHub Actions SSH) ────────────────
adduser --disabled-password --gecos "" $DEPLOY_USER
usermod -aG www-data $DEPLOY_USER
mkdir -p /home/$DEPLOY_USER/.ssh
chmod 700 /home/$DEPLOY_USER/.ssh

# Allow deploy user to reload php-fpm without password
echo "$DEPLOY_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.2-fpm" \
  >> /etc/sudoers.d/deploy-user

# ── 9. Clone the repository ────────────────────────────────
mkdir -p /var/www/it-helpdesk
chown $DEPLOY_USER:www-data /var/www/it-helpdesk
chmod 775 /var/www/it-helpdesk

sudo -u $DEPLOY_USER git clone https://github.com/faisal-dinus/it-helpdesk.git \
  /var/www/it-helpdesk

# ── 10. Backend first setup ────────────────────────────────
cd /var/www/it-helpdesk/it-helpdesk-backend

sudo -u $DEPLOY_USER composer install --no-dev --optimize-autoloader

# Create .env from example
sudo -u $DEPLOY_USER cp .env.example .env
# !! Edit .env manually after this script !!

sudo -u $DEPLOY_USER php artisan key:generate

# Storage permissions
chown -R $DEPLOY_USER:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

sudo -u $DEPLOY_USER php artisan storage:link

# ── 11. Frontend first build ───────────────────────────────
cd /var/www/it-helpdesk/it-helpdesk-frontend
sudo -u $DEPLOY_USER cp .env.example .env
sudo -u $DEPLOY_USER npm ci
sudo -u $DEPLOY_USER npm run build

# ── 12. Supervisor (queue worker) ─────────────────────────
apt install -y supervisor

cat > /etc/supervisor/conf.d/it-helpdesk-worker.conf <<SUP
[program:it-helpdesk-worker]
command=php /var/www/it-helpdesk/it-helpdesk-backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/it-helpdesk/it-helpdesk-backend
user=$DEPLOY_USER
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/it-helpdesk-worker.log
SUP

supervisorctl reread && supervisorctl update && supervisorctl start it-helpdesk-worker

# ── 13. Firewall ───────────────────────────────────────────
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# ── 14. Fail2ban ───────────────────────────────────────────
systemctl enable fail2ban
systemctl start fail2ban

echo ""
echo "======================================"
echo " Setup complete!"
echo " NEXT STEPS:"
echo " 1. Edit /var/www/it-helpdesk/it-helpdesk-backend/.env"
echo "    Set: DB_PASSWORD, APP_URL, GOOGLE_*, AZURE_*, MAIL_*"
echo " 2. Run: php artisan migrate --seed"
echo " 3. Add deploy user SSH public key to GitHub Actions secrets"
echo " 4. Update your DNS A record to point $DOMAIN → this server IP"
echo "======================================"
