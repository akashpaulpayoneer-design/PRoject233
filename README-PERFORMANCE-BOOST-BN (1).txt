TOP STUDIO — PERFORMANCE MONITOR & SAFE BOOST CENTER
Version: 1.0 (manual PageSpeed tests + controlled static-cache boost)

এই প্যাকেজে আছে:
- admin/index.php (Overview-তে ছোট Performance & Boost summary card)
- admin/performance.php (PageSpeed Monitor, History, Field Data, Boost Center)
- admin/performance-lib.php (API, database tables, private backup, safe cache/restore helpers)

যা পরিবর্তন করা হয়নি:
- public_html/index.php / public website homepage code
- config.php বা database credentials
- existing database tables/records used by the CMS
- images, CSS layout, JavaScript logic, slider, contact form, live chat, Google OAuth
- admin login, session, CSRF checks or existing admin menu structure

INSTALLATION
1) cPanel > File Manager > public_html/admin খুলুন।
2) বর্তমান admin/index.php ডাউনলোড করে আলাদা Backup রাখুন।
3) এই ZIP-এর admin folder-এর ফাইলগুলো public_html/admin-এ Upload/Extract করুন।
4) ZIP-এর admin/index.php দিয়ে public_html/admin/index.php Replace হবে।
5) admin/performance.php এবং admin/performance-lib.php নতুন ফাইল হিসেবে যোগ হবে।
6) config.php বা public_html/index.php Replace করবেন না।
7) Admin Panel-এ লগইন করুন। Overview-তে ছোট “Performance & Boost Center” কার্ড থাকবে। সেখান থেকে Open Performance Monitor চাপুন।

প্রথমবার চালুর সময়:
- Monitor দুটি additive database table তৈরি করবে: ts_performance_scans এবং ts_performance_backups।
- আগের CMS table, project, inquiry বা site setting পরিবর্তন/মুছে ফেলা হয় না।
- Hosting DB user-এর CREATE TABLE permission না থাকলে Monitor কাজ করবে না; তবে Overview-এর পুরোনো অংশ চালু থাকবে।

PAGESPEED TEST
- Mobile ও Desktop আলাদাভাবে Run Test চাপলে Google PageSpeed Insights API v5 থেকে Lab score, FCP, LCP, TBT, CLS, Speed Index, category scores এবং পাওয়া গেলে CrUX field data সংরক্ষণ হবে।
- প্রতিটি device-এর জন্য 10 মিনিট cooldown রাখা হয়েছে যাতে API quota অকারণে শেষ না হয়।
- Test automatic background-এ চলে না; এই সংস্করণে শুধু Admin-এর manual request-এ চলে। Daily scan চাইলে পরে cPanel Cron আলাদাভাবে configure করতে হবে।
- PageSpeed API occasional manual request-এ API key ছাড়া ব্যবহার করা যায়; ঘনঘন/automated request-এর জন্য Google API key সুপারিশ করে।
- প্রয়োজন হলে config.php-তে নিজে server-side এই লাইন যোগ করা যাবে (কখনো ChatGPT বা অন্য কাউকে Key পাঠাবেন না):
  define('PAGESPEED_API_KEY', 'YOUR_GOOGLE_API_KEY');
- API key ব্যবহার করলে Google Cloud-এ PageSpeed Insights API enable করুন এবং key-টি যথাযথভাবে restrict করুন।

SAFE BOOST / BACKUP / RESTORE
- প্রথম সংস্করণের Boost কেবল static-file Cache-Control / Expires policy প্রস্তাবিত block হিসেবে .htaccess-এ যোগ করে।
- Apply করার আগে .htaccess-এর private snapshot public_html-এর বাইরে home directory-তে .topstudio-private-performance-backups ফোল্ডারে রাখে। Backup folder তৈরি/লিখতে না পারলে Boost বন্ধ থাকে এবং কোনো website file পরিবর্তন করে না।
- শুধু CSS/JS ও image/font static asset caching-এর নিয়ম যোগ হয়; PHP, database, CMS data, image contents, CSS layout, JavaScript, slider, SEO tags, Google OAuth বা admin code পরিবর্তন হয় না।
- Rule block <IfModule> guard-এর মধ্যে থাকে এবং duplicate block যোগ করে না।
- Apply-এর পরে public homepage HTTP health check করা হয়। Check ব্যর্থ হলে আগের .htaccess স্বয়ংক্রিয়ভাবে restore করার চেষ্টা করে এবং error দেখায়।
- Monitor page-এ Backup & Restore history থাকে। Restore-এর আগেও বর্তমান .htaccess-এর safety snapshot তৈরি হয় এবং homepage check করা হয়।
- অন্য Boost (image conversion, JS defer/minify, PHP edits, database cleanup) এই সংস্করণে স্বয়ংক্রিয়ভাবে Apply হয় না। PageSpeed insight কেবল recommendation হিসেবে দেখানো হয়।

IMPORTANT
- প্রথমে staging/copy environment-এ পরীক্ষা করা উত্তম; shared hosting-এর Apache module availability ভিন্ন হতে পারে।
- .htaccess edit করার সময় Backup না থাকলে manual edit করবেন না।
- Optional cache block আলাদা marker দিয়ে চিহ্নিত: BEGIN/END TOP STUDIO SAFE STATIC CACHE.
- কোনো unexpected HTTP error হলে Monitor-এর Restore action ব্যবহার করুন অথবা cPanel backup থেকে আগের .htaccess ফিরিয়ে আনুন।
- এই প্যাকেজে config.php, API key বা database password নেই।
