TOP STUDIO — Performance Test Step Loader Update (v2)

এই Update শুধু Admin > Performance & Boost পেজের PageSpeed Test Loading UI উন্নত করে।

যা বদলেছে:
- Test বাটনে ক্লিক করার পরেই Overlay ও Spinner চালু হবে। পেজ খোলার সময় কোনো Spinner চলবে না।
- কাজের ধাপ একটির পর একটি দেখাবে: test প্রস্তুত, Google PageSpeed-এ সংযোগ, Lighthouse audit, report-এর অপেক্ষা।
- কোনো ভুয়া percentage দেখাবে না; Google PageSpeed API live progress percentage দেয় না।
- Browser back/forward cache থেকে ফিরে এলে stale disabled button ও loader reset হবে।
- Disabled button-এ ভুল করে wait cursor দেখাবে না; not-allowed cursor এবং setup note দেখাবে।
- যদি DB schema প্রস্তুত না থাকে, test button disabled থাকবে এবং setup warning দেখাবে; জোর করে enable করা হবে না।

যা পরিবর্তন হয় না:
- PageSpeed API request, timeout, quota/cooldown বা Google API key logic
- Database tables/data, Admin login, session, CSRF, Google OAuth
- Boost, backup/restore logic
- Website frontend, CSS, slider, images, content বা design

INSTALL:
1. cPanel > public_html/admin/ খুলুন।
2. বর্তমান performance.php ফাইলের backup নিন।
3. ZIP-এর admin/performance.php শুধু public_html/admin/performance.php-এ replace করুন।
4. অন্য কোনো file replace করবেন না।
5. Admin > Performance & Boost খুলুন। Idle অবস্থায় loader স্থির ও অদৃশ্য থাকবে। Mobile বা Desktop Test-এ click করলে step list একে একে দেখাবে।

IMPORTANT:
- Button যদি page load হওয়ার পরেও click না হয়, উপরের setup warning দেখুন। এটি database tables প্রস্তুত না থাকার ইঙ্গিত হতে পারে।
- 429 API quota/rate limit এই UI Update দূর করে না; server আগের cooldown logic অনুসরণ করবে।
- Step list হলো high-level activity indicator; remote Google API live internal progress দেয় না। ফলাফল ফিরে এলে server report save করে page refresh করবে।
- আগে backup নিন। এই Update Live Hosting-এ আগে পরীক্ষা করা হয়নি।
