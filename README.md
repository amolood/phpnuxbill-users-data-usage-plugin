# User Data Usage Plugin for PHPNuxBill

A data-usage analytics plugin for [PHPNuxBill](https://github.com/hotspotbilling/phpnuxbill/) (PHP Mikrotik Billing). It turns raw FreeRADIUS accounting data into clear, actionable usage reports for both administrators and customers — per-user totals, quota tracking, daily trends, filtering, and CSV export.

---

## Features

- **Summary dashboard** — at-a-glance cards for total data, download, upload, users online, total users, and sessions.
- **Per-user view** — aggregated download/upload/total per customer, sorted by usage, with quota progress bars.
- **Sessions view** — detailed, paginated log of every accounting session.
- **Quota tracking** — shows each user's plan data-limit vs. usage as a colour-coded progress bar (green / amber / red).
- **Daily usage trend** — line chart of download/upload per day.
- **Filtering** — by username, date range, and connection status (online / offline).
- **CSV export** — export the current view for billing reconciliation or reporting.
- **Customer self-service page** — each customer sees their own usage, quota, charts, and session history.
- **Dual backend support** — works with **both** of PHPNuxBill's FreeRADIUS modes (REST and SQL) automatically; see [Compatibility](#compatibility).
- **Multi-language** — all interface strings use PHPNuxBill's translation system.
- **Read-only & safe** — the plugin only reads data; it never creates, alters, or deletes any table or record.

---

## Requirements

| Requirement | Notes |
|-------------|-------|
| PHPNuxBill | Latest version recommended |
| PHP | 8.2 or higher |
| Database | MySQL / MariaDB |
| FreeRADIUS | **Required** — must be enabled in PHPNuxBill (*Settings → Radius*) |

> **Why FreeRADIUS is required:** this plugin reports on the FreeRADIUS accounting table. Without Radius enabled and a router sending accounting data, there is nothing to display. If no accounting table is found, the plugin shows a clear notice instead of an error.

---

## Installation

### Option 1 — Plugin Installer (recommended)

1. In your PHPNuxBill admin dashboard, open the **Plugin Manager**:
   `https://your-domain/index.php?_route=pluginmanager`
2. Paste this repository URL into the install field:
   `https://github.com/amolood/phpnuxbill-users-data-usage-plugin`
3. Click **Install**.

### Option 2 — Manual installation

Copy the files into your PHPNuxBill installation:

```
data_usage_admin.php   →  system/plugin/data_usage_admin.php
data_usage_user.php    →  system/plugin/data_usage_user.php
ui/data_usage_admin.tpl →  system/plugin/ui/data_usage_admin.tpl
ui/data_usage_user.tpl  →  system/plugin/ui/data_usage_user.tpl
```

No database changes are required.

---

## Usage

- **Admin:** open **Services → User Data Usage**. Use the *Per User* / *Sessions* toggle, the filters, and the **CSV** button as needed.
- **Customer:** a **User Data Usage** entry appears in the customer dashboard menu, showing their own usage, quota, and charts.

---

## Compatibility

PHPNuxBill supports two FreeRADIUS integration modes, which store accounting data differently. This plugin **auto-detects** which one is in use and adapts:

| Mode | Accounting table | Database / connection | Online status |
|------|------------------|------------------------|----------------|
| **FreeRADIUS REST** | `rad_acct` | Main PHPNuxBill database | `acctstatustype = Start` |
| **FreeRADIUS SQL**  | `radacct`  | Separate `radius` database | session has no stop time |

No configuration is needed — the plugin verifies the required columns exist before using a backend, and falls back gracefully if neither is available.

---

## Screenshots

![Admin view](https://github.com/amolood/phpnuxbill-users-data-usage-plugin/blob/main/user.png)

---

## Contributing

Issues and pull requests are welcome. If you find a bug or want a feature, please open an [issue](https://github.com/amolood/phpnuxbill-users-data-usage-plugin/issues).

<a href="https://github.com/amolood/phpnuxbill-users-data-usage-plugin/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=amolood/phpnuxbill-users-data-usage-plugin" />
</a>

---

## License

Released under the terms of the [LICENSE](LICENSE) file in this repository.
