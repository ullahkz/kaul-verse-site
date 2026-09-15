# Kaul Verse Cards

## Cards

1. Open **Cards → Card Groups** and add groups such as Services or Products. Groups can be renamed, nested, and added at any time. A card can belong to several groups. Changing a group's slug requires updating shortcodes using that slug.
2. Open **Cards → Add New**. Cards use a simple form editor: the title field is at the top, the supporting text editor is below, and Card Groups are in the sidebar. The **How to use this card** panel displays group shortcodes after saving. Enter a title and supporting text, then optionally add a main card image, small thumbnail, subheading, and up to two buttons in **Card Details**. Each button needs a label and destination page.
3. Assign groups, set the Order number (lowest first), and publish.
4. Copy the shortcode from **Card Groups** into a page's **Shortcode** block:

```text
[kaulverse_cards group="products" columns="3" limit="12"]
```

Columns accept 1–4 and adapt to smaller screens. The default limit is 12, with a maximum of 100 cards per shortcode; there is no pagination. Parent groups include child groups. Omit `group` to show all cards. Equal Order values sort by title, then ID. Unknown or empty groups output nothing.

Only published, non-password-protected cards appear. Buttons only link to published, non-password-protected pages. Sharing uses the first valid selected page, with native sharing or a copy-link fallback. Cards have no public detail pages or archives.

All visual fields are optional. Keep an administrative title for identification and check **Hide title** when needed. Add image alternative text in the Media Library. Main images use a cropped 16:9 display area.

Cards and groups use native WordPress storage, with no external dependencies. They can represent products for display; pricing, inventory, checkout, and per-group ordering are not included. Deactivation retains content. Card Details fields are not included in editor revision restoration.


### Image placement

In **Card Details → Main image position**, choose **Top** or **Bottom**, then save. The main card image spans the full card width above or below the text and buttons. Top is the default. The optional small thumbnail remains beside the heading.

Select the image using **Card Details → Main card image → Choose main image**. Keep supporting text in the text editor. If an image was previously inserted into the text, remove that inline image manually to avoid displaying it twice.
