TOP STUDIO — Performance Monitor Compatibility & Stability Patch v1.1.1
বাংলা Installation Guide

এই Patch কেন প্রয়োজন:
আপনার error_log-এ পাওয়া গেছে:
TOP STUDIO performance schema error: Call to undefined function ts_perf_schema_exists()

এটি এখন পর্যন্ত Database CREATE permission ব্যর্থ হওয়ার প্রমাণ নয়। আপনার ইনস্টল করা Performance Test page (Step Loader V2) ts_perf_schema_exists() helper ব্যবহার করছে, কিন্তু আগের performance-lib.php-তে helper function-টি ছিল না। ফলে database table check শুরু হওয়ার আগেই PHP error হচ্ছিল।

এই Patch-এ চারটি compatible ফাইল আছে:
1) admin/performance-lib.php — ts_perf_schema_exists() helper যোগ করে; Performance Monitor table setup ও PageSpeed request timeout compatibility.
2) admin/index.php — Overview-তে optional table শুধু read/check করে; প্রতি Dashboard visit-এ CREATE TABLE চালায় না। Google reports-এর জন্য cache-only read ব্যবহার করে, যাতে Overview খুললে serial live Google API request অপেক্ষা না করে।
3) admin/google-api.php — cache-only parameter সমর্থন করে; explicit refresh action আগের মতো remote API request করতে পারে।
4) admin/google-sync.php — explicit Google refresh চলার সময় PHP session lock release করে, যাতে অন্য Admin tab আটকে না থাকে।

INSTALLATION:
1. cPanel File Manager-এ public_html/admin খুলুন.
2. নিচের চারটি বর্তমান ফাইল Download করে Backup নিন:
   - index.php
   - google-api.php
   - google-sync.php
   - performance-lib.php
3. এই ZIP Extract করুন; ZIP-এর admin folder-এর ভিতরের চারটি ফাইল public_html/admin/ এ Replace করুন.
4. আপনার বর্তমানে ইনস্টল করা admin/performance.php (Premium Step Loader V2) পরিবর্তন করবেন না। এই Patch-এ performance.php ইচ্ছাকৃতভাবে নেই, যাতে আপনার step-by-step loader অক্ষত থাকে।
5. _common.php, _layout.php, login.php, settings.php, config.php, OAuth token files, public_html/index.php, CSS, Database credentials বা website content পরিবর্তন করবেন না.
6. Admin logout/login করে প্রথমে Overview খুলুন; তারপর Performance Monitor খুলে Test চেষ্টা করুন.

গুরুত্বপূর্ণ:
- এখনই Database user privilege বা password পরিবর্তন করবেন না.
- বর্তমান error_log-এ undefined function error আছে, actual SQL CREATE permission-denied error নেই। এই Patch-এর পরে যদি error_log-এ SQLSTATE 42000 / CREATE command denied বা access denied দেখা যায়, তখনই cPanel MySQL Database privileges আলাদাভাবে পরীক্ষা করতে হবে.
- Google PageSpeed API 429 rate limit আলাদা সমস্যা। এই Patch 429 quota বাড়ায় না; repeated test না করে cooldown শেষে আবার চেষ্টা করুন অথবা নিজের PageSpeed Insights API key configure করুন.
- Google OAuth token expired/revoked error-ও আলাদা। Overview এখন cache-only থাকবে; Google Sync manually চালালে expired token-এর জন্য reconnect প্রয়োজন হতে পারে.
- Patch তৈরি ও PHP syntax পরীক্ষা করা হয়েছে, কিন্তু আপনার live hosting-এ install করে পরীক্ষা করা হয়নি. Backup রেখে এক ফাইল করে Replace করুন.

ZIP contents:
- admin/index.php
- admin/google-api.php
- admin/google-sync.php
- admin/performance-lib.php
- README-PERFORMANCE-MONITOR-COMPATIBILITY-BN.txt
