TOP STUDIO — PERFORMANCE & SECURITY SUITE V2
একটি Compact Control Center Update

এই প্যাকেজটি আপনার বর্তমান Admin Source-এর ওপর ভিত্তি করে তৈরি। বিদ্যমান Performance Monitor, Step Loader, PageSpeed API, Safe Cache Boost ও .htaccess snapshot/restore ফাংশন বাদ দেওয়া হয়নি; সেগুলো আগের Performance পেজেই থাকবে। নতুন Control Center থেকে একই জায়গায় Performance summary, read-only Security Scan, Website Health Scan এবং Boost/Backup shortcut পাওয়া যাবে।

ZIP-এ আছে:
1) admin/_layout.php — Sidebar-এ একটি মাত্র “Control Center” menu item যোগ করে।
2) admin/control-center.php — Compact overview, Security, Website Health ও existing Performance/Boost shortcuts।
3) admin/suite-lib.php — Read-only scanners, private scan reports এবং private activity log।

যা পরিবর্তন করা হবে না:
- public_html/index.php বা কোনো public website page
- Admin login.php, config.php, Database credentials বা existing CMS tables
- Google OAuth config/token, PageSpeed API key বা Google reporting cache
- CSS theme assets/admin.css, public site assets, logo, images বা upload content
- existing admin/performance.php ও admin/performance-lib.php
- Projects, Services, Inquiries, Contact Form, Live Chat, Hero Slider বা SEO settings

INSTALLATION:
1. cPanel > File Manager > public_html/admin খুলুন।
2. বর্তমান _layout.php ডাউনলোড করে Backup রাখুন।
3. ZIP Extract করুন; ZIP-এর admin folder-এর তিনটি ফাইল public_html/admin/ এ বসান।
4. শুধু _layout.php Replace হবে। control-center.php ও suite-lib.php নতুন ফাইল হিসেবে যোগ হবে।
5. config.php, index.php, performance.php, performance-lib.php বা অন্য কোনো ফাইল Replace করবেন না।
6. Admin logout/login করে Sidebar থেকে Control Center খুলুন।

কীভাবে কাজ করে:
- Control Center page load-এ কোনো PageSpeed API call, remote scan বা database write চলে না। এটি শুধু আগে সংরক্ষিত Performance result পড়ার চেষ্টা করে।
- Security Scan ও Website Health Scan কেবল আপনি আলাদা করে Button চাপলে চলে।
- Security Scan HTTPS headers, সাধারণত সংবেদনশীল public paths, login source-এ CSRF/session rotation signal, session cookie flags, config file permission এবং private Google OAuth folder-এর অবস্থান পরীক্ষা করে।
- Health Scan SELECT 1, গুরুত্বপূর্ণ public URLs (homepage, About, Services, Projects, Concepts, Contact, Privacy, Terms, sitemap, robots), প্রয়োজনীয় file/folder, PHP extensions ও hosting disk information পরীক্ষা করে।
- Scan result ও scan activity public_html-এর বাইরে .topstudio-private-suite folder-এ private JSON হিসেবে রাখা হয়। কোনো scan-এর জন্য নতুন Database Table প্রয়োজন নেই।
- কোনো scan PHP, CSS, JavaScript, .htaccess, Database record, Login setting, OAuth বা website content পরিবর্তন করে না। কোনো security fix auto-apply হয় না।
- Performance Test, Premium Step Loader, Google PageSpeed cooldown, Safe Cache Boost এবং .htaccess snapshot/restore আগের performance.php page-এ থাকে।

BACKUP LIMIT:
- Existing Performance Monitor-এর current backup/restore function .htaccess configuration snapshot-এর জন্য। এটি Full Website বা Full Database Backup নয়। সম্পূর্ণ website/database backup-এর জন্য cPanel > Backup/Wizard বা hosting provider-এর full account backup ব্যবহার করুন।
- Safe Cache Boost এখনও কেবল managed static-file cache block যোগ করে; image conversion, JS/CSS minification বা PHP/Database cleanup auto-apply করে না।

SECURITY LIMIT:
- Missing security headers scanner-এ Recommendation হিসেবে আসতে পারে; এগুলো স্বয়ংক্রিয়ভাবে যোগ করা হবে না, কারণ CSP/HSTS ভুলভাবে প্রয়োগ করলে Google Fonts, images, forms, chat বা analytics ভেঙে যেতে পারে।
- Login rate-limit/2FA, firewall/WAF বা server-level hardening এই read-only update থেকে পরিবর্তিত হয় না। এগুলো আলাদা compatibility review ও অনুমোদন চায়।
- 200 response পাওয়া কোনো sensitive path scanner-এ Critical হিসেবে দেখাবে; false positive হলে response/redirect manually যাচাই করুন।

TESTING:
- PHP syntax check (এই তিনটি file) চালানো হয়েছে।
- আপনার Live Hosting-এ install করে পরীক্ষা করা হয়নি। প্রথমে _layout.php Backup রাখুন। Install-এর পর Admin Login, Overview, Performance Monitor, Google OAuth, Boost, Backup/Restore, Live Chat ও Contact Form পরীক্ষা করুন।
