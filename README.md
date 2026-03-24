# Zoho Lead Mapping for WordPress 🚀

![WordPress](https://img.shields.io/badge/WordPress-Tested%20Up%20To%206.4-blue)
![PHP](https://img.shields.io/badge/PHP-%3E%3D%207.4-8892BF)
![License](https://img.shields.io/badge/License-GPLv2-brightgreen)
![Pricing](https://img.shields.io/badge/Pricing-100%25%20Free-success)

A powerful, fully-unlocked WordPress plugin that seamlessly connects your website to Zoho CRM. Build customizable lead capture forms and submit data—including file attachments and product associations—directly into your Zoho CRM account without relying on expensive third-party tools like Zapier.

## ✨ Key Features

*   **Drag & Drop Form Builder:** Easily select, reorder, and configure form fields right from your WordPress dashboard.
*   **Direct Zoho V8 API Integration:** Highly secure connection using modern OAuth 2.0 authentication.
*   **File Upload Support:** Allow users to upload files (PDFs, images, docs) which are automatically attached to the newly created Lead in Zoho.
*   **Product Sync & Association:** Fetch your products directly from Zoho CRM and display them in a dropdown. The selected product is automatically associated with the new lead.
*   **Customizable Messages:** Tailor success, error, and validation messages with dynamic placeholders (e.g., `{first_name}`).
*   **Smart Fallbacks:** Set default "Lead Source" values for incoming leads.
*   **No Restrictions:** Fully open-source and free to use with zero hidden paywalls.

## 🛠️ Installation

1. Download the latest version of the plugin as a `.zip` file from this repository.
2. Log into your WordPress admin dashboard.
3. Navigate to **Plugins > Add New > Upload Plugin**.
4. Upload the downloaded `.zip` file and click **Install Now**.
5. Click **Activate Plugin**.
6. A new menu item called **Zoho Lead Capture** will appear in your WordPress sidebar.

## ⚙️ Configuration & Zoho API Setup

To connect the plugin to your Zoho CRM, you need to generate OAuth 2.0 credentials. 

### Step 1: Create a Zoho API Client
1. Go to the [Zoho API Console](https://api-console.zoho.com/).
2. Click **Add Client** and choose **Server-based Applications**.
3. Enter your Client Name, Homepage URL, and Authorized Redirect URIs (you can use something like `https://oauth.tools/callback` for initial token generation).
4. Click **Create** to get your **Client ID** and **Client Secret**.

### Step 2: Generate a Refresh Token
Generate a refresh token using the following scopes required by this plugin:
`ZohoCRM.modules.ALL,ZohoCRM.users.READ,ZohoCRM.settings.ALL,ZohoCRM.org.READ`

### Step 3: Enter Credentials in WordPress
1. In your WordPress dashboard, go to **Zoho Lead Capture > Zoho CRM Settings**.
2. Enter your **Client ID**, **Client Secret**, and **Refresh Token**.
3. Select your API Domain (US, EU, or IN) and Account Domain.
4. Click **Save Zoho CRM Settings**.

## 🎨 Usage

### 1. Build Your Form
Navigate to the **Form Builder** tab. Check the boxes next to the fields you want to include (First Name, Email, File Upload, etc.). Drag and drop them into your preferred order, change the labels, and mark them as required or optional.

### 2. Sync Products (Optional)
If you want users to select a product they are interested in, go to the **Product Sync** tab and click **Refresh Products from Zoho CRM**. Enable the "Product Select" field in the form builder.

### 3. Display the Form
Once your form is configured, use the following shortcode anywhere on your site (Pages, Posts, or Widgets) to display the lead capture form:

```text
[zoho_lead_maping]
