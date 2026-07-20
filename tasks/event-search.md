Goal: Search bar on home page that filters events and news, able to use arrows to move up and down, able to click search or press enter to search, able to show results even when searching by category, fuzzy searches, 'X' button on right end to clear search bar

What Columns: For events we will search title, description, and venue. For news we will search title and link. 

Why: Users have to click and scroll to find things

Scope(v2): events (within one past month to one future month) + news (only from past month until today) only; extend to deals, discussions, resources later

Files Touched: src/helpers.php, views/pages/dashboard.php, style.css

What Screens: home page

Design: server-side, all SQL through fetchAll()



## Test Verification
Once built, test by loading home page and trying these:

1. Type tolerance: type "libary" -> events/venues containing "Library" should still appear (proves fuzzy matching works)

2. Basic search: type "music" -> 'Music' category's events appear (proves text + category matching works) 

3. Category search: type category name like "Arts" or "Sports" -> only events in that category appear (proves lookup_values join works)

For each test: we type test words, look at results, confirm expected items show up 
