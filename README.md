# ChatKit Embed for WordPress

**ChatKit Embed** is a WordPress plugin that provides a frontend UI for workflows created with the **OpenAI Agent Builder**.
It allows you to securely embed an agent-powered chat experience on any page or post using a shortcode.

The plugin acts purely as a **UI and embedding layer**. All agent logic, tools, and prompts are managed in OpenAI.

---

## Requirements

* WordPress 6.0+
* PHP 8.0+
* An OpenAI account with access to Agent Builder
* A valid OpenAI API key

---

## Installation

1. Download or clone this repository.
2. Upload the `chatkit-embed` directory to `/wp-content/plugins/`.
3. In WordPress Admin, go to **Plugins → Installed Plugins**.
4. Activate **ChatKit Embed**.

---

## Quick Start

1. Go to **Settings → MyChatKit** and enter your **OpenAI API key**.
2. Add your site’s domain to the OpenAI Domain Allowlist:
   [https://platform.openai.com/settings/organization/security/domain-allowlist](https://platform.openai.com/settings/organization/security/domain-allowlist)
3. Open **Settings → ChatKit Widget** and configure the widget options.
4. Copy the generated shortcode.
5. Paste the shortcode into any page or post and publish.

The ChatKit UI will load and connect to your OpenAI Agent Builder workflow.

---

## Configuration

### OpenAI API Key

* Navigate to **Settings → MyChatKit**
* Paste your OpenAI API key
* Save changes

The API key is required for all frontend interactions with the agent.

---

### Domain Allowlist (Required)

For browser-based usage, OpenAI requires explicit domain approval.

Add your production domain (for example `example.com`) here:
[https://platform.openai.com/settings/organization/security/domain-allowlist](https://platform.openai.com/settings/organization/security/domain-allowlist)

Without this step, the widget will not load on the frontend.

---

### Widget Settings

Go to **Settings → ChatKit Widget** to configure:

* Agent / workflow identifier
* Widget behavior and UI options
* Optional limits or feature toggles

Save your settings when finished.

---

## Usage

Insert the generated shortcode into any WordPress page or post.

Example:

```text
[chatkit_widget    workflow="wf_123..."     mode="inline"]
```

You may use the shortcode multiple times across different pages.

---

## How It Works

1. The plugin renders a ChatKit-based UI on the frontend.
2. Requests are sent directly to OpenAI using your API key.
3. The embedded UI connects to the configured Agent Builder workflow.
4. All reasoning, tools, and prompts remain managed in OpenAI.

No agent logic is stored or executed in WordPress.

---

## Security Notes

* Never expose your API key outside WordPress admin settings.
* Always restrict domains via OpenAI Domain Allowlist.
* Use staging environments when testing new agents or workflows.

---

## Troubleshooting

**Widget does not load**

* Check that your domain is allowlisted in OpenAI settings.
* Verify the API key is valid and active.
* Ensure the agent/workflow ID is correct.

**API errors**

* Check OpenAI usage limits and billing status.
* Review browser console for blocked requests.

---

## License

GPL-2.0 or later

