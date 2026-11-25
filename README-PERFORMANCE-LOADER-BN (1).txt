TOP STUDIO — Premium PageSpeed Test Loader Update

এই Update শুধু Performance Test বাটনের Loading UI পরিবর্তন করে।

যা যোগ হয়েছে:
- Black & Champagne Gold theme-এর premium modal overlay
- Spinning double-ring TS mark
- Mobile/Desktop অনুযায়ী আলাদা Test label
- Indeterminate progress sweep (মিথ্যা percentage নয়)
- Test চলাকালে duplicate submission আটকানো
- Screen reader-এর জন্য status/live announcement
- Reduced-motion preference সম্মান করা

যা পরিবর্তন হয় না:
- PageSpeed API request logic
- Google API quota/cooldown logic
- Database schema বা data
- Admin login/session/CSRF code
- Website frontend, CSS, slider, content বা design
- Boost/Backup/Restore logic

INSTALL:
1. cPanel > public_html/admin/ খুলুন।
2. বর্তমান performance.php-এর একটি backup ডাউনলোড করুন।
3. ZIP-এর admin/performance.php ফাইলটি public_html/admin/performance.php-এ upload করে শুধু এই ফাইলটি replace করুন।
4. অন্য কোনো PHP, CSS, JS, database বা config file replace করবেন না।
5. Admin > Performance & Boost খুলে Mobile অথবা Desktop Test চাপুন।
6. Premium loading overlay দেখাবে; request শেষ হলে result বা error flash message দেখাবে।

NOTES:
- Google PageSpeed API প্রকৃত progress percentage দেয় না; তাই overlay-তে percentage দেখানো হয় না।
- HTTP 429 হলে server-side quota/cooldown message আগের মতোই দেখাবে।
- এই Update API timeout বা quota limit পরিবর্তন করে না; শুধু test চলাকালে UI feedback যোগ করে।
- আগে backup নিন। ইনস্টল করার পর পরীক্ষা করুন।
