=== Woo Hierarchical Category Menu ===
Contributors: wbl
Tags: woocommerce, menu, categories, hierarchy, shortcode
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Insert an automatically generated WooCommerce category tree anywhere in a WordPress menu or page.

== Description ==

Under Appearance > Menus, open "Woo Category Shortcode", add its item, then drag it below Shop or any other menu item. Category children are generated dynamically.

The shortcode also works in posts, pages, widgets, and builders:

`[woo_category_menu depth="2" hide_empty="1"]`

Attributes:

* `depth` - Maximum levels; 0 is unlimited.
* `parent` - Children of this product category term ID; 0 starts at the top.
* `hide_empty` - 1 hides categories without products; 0 shows them.
* `orderby` - name, slug, term_id, count, or menu_order.
* `order` - ASC or DESC.
* `include` and `exclude` - Comma-separated category IDs.
* `show_count` - 1 displays product counts.

Example: `[woo_category_menu parent="25" depth="3" hide_empty="0" orderby="menu_order" show_count="1"]`

== Installation ==

1. Activate this plugin with WooCommerce active.
2. Open Appearance > Menus.
3. Add the Woo Category Shortcode item.
4. Drag it under the desired parent item and save the menu.

== Changelog ==

= 2.0.0 =

* Added a draggable dynamic menu item and configurable shortcode output.
