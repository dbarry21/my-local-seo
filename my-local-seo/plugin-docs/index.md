# My Local SEO Plugin Documentation

## Welcome to My Local SEO

My Local SEO is a comprehensive WordPress plugin designed for local businesses and SEO professionals. It provides powerful tools for managing local business information, generating AI-powered content, optimizing schema markup, and creating dynamic service area pages.

## Key Features

### AI-Powered Content Generation
- **AI Taglines**: Generate 3-4 benefit-focused tagline options for services in HTML format
- **AI FAQs**: Create structured FAQ content with schema markup
- **AI About Area**: Generate location-specific "About the Area" content  
- **AI Excerpts**: Automatic meta descriptions and excerpts
- **AI Geo Content**: Bulk generate location-based content

### Service Management
- **Service Grid**: Responsive grid display with customizable columns (2-6)
- **Service Taglines**: Manual or AI-generated taglines with character counter
- **Service Areas**: Hierarchical service area management with parent-child relationships
- **Service Schema**: Automatic Service schema markup for Google

### Schema & SEO
- **Organization Schema**: Business information with multiple locations
- **Local Business Schema**: Location-specific schema markup
- **Service Schema**: Service-specific schema with provider information
- **FAQ Schema**: Structured FAQ data for rich snippets
- **About Page Schema**: AboutPage schema for company pages

### Location Features
- **Dynamic Location Tags**: [city_state], [city_only], [county] shortcodes
- **Service Area Grids**: Display service areas in responsive grids
- **Google Maps Integration**: Static map generation with preview
- **Location Hierarchy**: Parent-child service area relationships

### Bulk Operations
- **Bulk Meta Generation**: Generate meta titles and descriptions
- **Bulk FAQ Generation**: Create FAQs for multiple posts
- **Bulk Tagline Generation**: Generate taglines for services
- **Google Maps Bulk**: Generate maps for multiple locations

## Quick Start Guide

### Step 1: Configure Organization Settings

1. Go to **My Local SEO → Schema → Organization**
2. Enter your business information:
   - Organization Name
   - URL
   - Phone Number
   - Address
   - Logo
3. Click **Save Changes**

### Step 2: Set Up API Integration

1. Go to **My Local SEO → API Integration**
2. Enter your **OpenAI API Key**
3. Configure default settings:
   - Model: claude-sonnet-4-20250514 (recommended)
   - Temperature: 0.7
   - Max Tokens: 4000
4. Click **Save Settings**

### Step 3: Create Services

1. Go to **Services → Add New**
2. Enter service title and description
3. Add featured image
4. In the **Service Tagline** metabox:
   - Click **Generate with AI** for automatic tagline
   - Or enter manually
5. Publish the service

### Step 4: Add Shortcodes to Pages

Common shortcodes:
- Services grid: `[service_grid]`
- Location: `[city_state]`
- FAQs: `[faq_schema_accordion]`

### Step 5: Enable Schema

1. Go to **My Local SEO → Schema**
2. Enable desired schema types:
   - **Organization**: Always enable
   - **Local Business**: If you have physical locations
   - **Service**: For service pages
   - **FAQ**: If using FAQ content
3. Save settings

## Common Workflows

### Creating a Service Area Landing Page

1. Create a new Page or Service Area post
2. Add location fields (city_state, county)
3. Add this content:
   - Headline: `Services in [city_state]`
   - Intro: `[about_the_area]`
   - Services: `[service_grid]`
   - FAQs: `[faq_schema_accordion]`
4. Generate AI content via AI tabs

### Bulk Generating Taglines

1. Go to **My Local SEO → AI → Taglines**
2. Select post type (Services, Pages, etc.)
3. Select posts (or click Select All)
4. Configure options:
   - Skip existing or Overwrite
5. Click **Generate Taglines**
6. Review results in log

### Setting Up Service Schema

1. Go to **My Local SEO → Schema → Service**
2. Enable Service Schema
3. Assign service pages or use Service CPT
4. Configure service subtype (optional)
5. Save settings
6. Schema automatically appears on service pages

## System Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- OpenAI API key (for AI features)
- Google Maps API key (for map features)

## Support & Documentation

- **Shortcodes**: See the Shortcodes tab for complete reference
- **Admin Tabs**: See Tabs & Subtabs for all admin features
- **Tutorials**: See Tutorials tab for step-by-step guides
- **Release Notes**: See Release Notes for version history

## Tips for Success

1. **Start with Organization Schema**: This is the foundation for all other schema types
2. **Use AI Features Wisely**: Generate content for 2-3 posts first to test quality
3. **Test Shortcodes**: Preview pages before publishing
4. **Backup Settings**: Export your configuration regularly
5. **Monitor Token Usage**: Track OpenAI API usage to stay within budget
6. **Enable Caching**: Use caching plugins for better performance
7. **Mobile Testing**: Always test on mobile devices

## Getting Help

If you encounter issues:
1. Check the Tutorials tab for step-by-step guides
2. Review the Shortcodes tab for usage examples
3. Verify API keys in Settings
4. Clear WordPress and browser cache
5. Check browser console for JavaScript errors

## What's Next?

- Explore the **Shortcodes** tab to learn all available shortcodes
- Visit **Tabs & Subtabs** to understand admin features  
- Check **Tutorials** for detailed workflow guides
- Review **API Reference** for developer documentation
