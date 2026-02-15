<?php
/**
 * My Local SEO – Shortcode Documentation Data
 * File: admin/docs/shortcode-data.php
 * 
 * Comprehensive documentation for all plugin shortcodes
 */

if (!defined('ABSPATH')) exit;

function mlseo_compile_shortcode_documentation() {
    return [
        
        // ============================================================
        // LOCATION & GEOGRAPHY SHORTCODES
        // ============================================================
        
        [
            'name' => 'city_state',
            'category' => 'location',
            'description' => 'Displays the city and state from the current post or ancestor. Perfect for dynamic location-based content.',
            'basic_usage' => '[city_state]',
            'attributes' => [
                'post_id' => [
                    'default' => '0',
                    'description' => 'Specific post ID (0 = current post)'
                ],
                'from' => [
                    'default' => 'self',
                    'description' => 'Where to get value: self, parent, ancestor'
                ],
                'field' => [
                    'default' => 'city_state',
                    'description' => 'ACF field name to read'
                ],
                'delimiter' => [
                    'default' => ',',
                    'description' => 'Separator between city and state'
                ],
                'normalize' => [
                    'default' => '0',
                    'description' => '1 = clean formatting, 0 = raw'
                ],
                'state_upper' => [
                    'default' => '0',
                    'description' => '1 = uppercase state abbreviation'
                ],
                'prefix' => [
                    'default' => '',
                    'description' => 'Text before output'
                ],
                'suffix' => [
                    'default' => '',
                    'description' => 'Text after output'
                ],
                'fallback' => [
                    'default' => '',
                    'description' => 'Text if no value found'
                ]
            ],
            'examples' => [
                ['label' => 'Basic usage', 'code' => '[city_state]'],
                ['label' => 'With prefix', 'code' => '[city_state prefix="Serving "]'],
                ['label' => 'From parent page', 'code' => '[city_state from="parent"]'],
                ['label' => 'Uppercase state', 'code' => '[city_state state_upper="1"]'],
                ['label' => 'Custom delimiter', 'code' => '[city_state delimiter=" - "]']
            ],
            'tips' => [
                'Use from="ancestor" to find the nearest parent with location data',
                'prefix and suffix are useful for creating natural sentences',
                'Set a fallback to avoid empty output on non-location pages'
            ]
        ],

        [
            'name' => 'city_only',
            'category' => 'location',
            'description' => 'Displays only the city name from location data, without the state.',
            'basic_usage' => '[city_only]',
            'attributes' => [
                'post_id' => [
                    'default' => '0',
                    'description' => 'Specific post ID (0 = current post)'
                ],
                'from' => [
                    'default' => 'self',
                    'description' => 'Where to get value: self, parent, ancestor'
                ],
                'field' => [
                    'default' => 'city_state',
                    'description' => 'ACF field name to read'
                ],
                'fallback' => [
                    'default' => '',
                    'description' => 'Text if no value found'
                ]
            ],
            'examples' => [
                ['label' => 'Basic usage', 'code' => '[city_only]'],
                ['label' => 'From parent', 'code' => '[city_only from="parent"]'],
                ['label' => 'With fallback', 'code' => '[city_only fallback="your area"]']
            ],
            'tips' => [
                'Useful for headlines where full city, state is too long',
                'Pairs well with city_state for varied content'
            ]
        ],

        [
            'name' => 'county',
            'category' => 'location',
            'description' => 'Displays the county name from location data.',
            'basic_usage' => '[county]',
            'attributes' => [
                'post_id' => [
                    'default' => '0',
                    'description' => 'Specific post ID (0 = current post)'
                ],
                'from' => [
                    'default' => 'self',
                    'description' => 'Where to get value: self, parent, ancestor'
                ],
                'field' => [
                    'default' => 'county',
                    'description' => 'ACF field name to read'
                ],
                'fallback' => [
                    'default' => '',
                    'description' => 'Text if no value found'
                ]
            ],
            'examples' => [
                ['label' => 'Basic usage', 'code' => '[county]'],
                ['label' => 'With suffix', 'code' => 'Serving [county] County']
            ],
            'tips' => [
                'Requires county field to be set in ACF',
                'Great for service area descriptions'
            ]
        ],

        // ============================================================
        // SERVICES & SERVICE AREAS SHORTCODES
        // ============================================================

        [
            'name' => 'service_grid',
            'category' => 'services',
            'description' => 'Displays a responsive grid of service posts with images, titles, taglines/excerpts, and buttons. Highly customizable layout.',
            'basic_usage' => '[service_grid]',
            'attributes' => [
                'columns' => [
                    'default' => '4',
                    'description' => 'Number of columns on desktop: 2, 3, 4, or 6'
                ],
                'subtext' => [
                    'default' => 'tagline',
                    'description' => 'Show tagline or excerpt: tagline, excerpt, or none'
                ],
                'button_text' => [
                    'default' => 'Learn More',
                    'description' => 'Text for the button'
                ],
                'button' => [
                    'default' => '1',
                    'description' => '1 = show button, 0 = hide button'
                ],
                'button_class' => [
                    'default' => 'btn btn-primary mt-2',
                    'description' => 'CSS classes for button styling'
                ],
                'button_target' => [
                    'default' => '',
                    'description' => 'Link target: _blank for new window'
                ],
                'show_excerpt' => [
                    'default' => '1',
                    'description' => '1 = show subtext, 0 = hide'
                ],
                'excerpt_words' => [
                    'default' => '20',
                    'description' => 'Number of words in excerpt'
                ],
                'image_crop' => [
                    'default' => '0',
                    'description' => '1 = uniform height, 0 = natural'
                ],
                'image_height' => [
                    'default' => '220',
                    'description' => 'Image height in pixels (if image_crop=1)'
                ],
                'featured_first' => [
                    'default' => '0',
                    'description' => '1 = first card larger, 0 = all same size'
                ],
                'center' => [
                    'default' => '1',
                    'description' => '1 = center incomplete rows, 0 = left align'
                ],
                'orderby' => [
                    'default' => 'menu_order title',
                    'description' => 'How to order posts'
                ],
                'order' => [
                    'default' => 'ASC',
                    'description' => 'Sort direction: ASC or DESC'
                ]
            ],
            'examples' => [
                ['label' => 'Default 4 columns', 'code' => '[service_grid]'],
                ['label' => '3 columns with excerpts', 'code' => '[service_grid columns="3" subtext="excerpt"]'],
                ['label' => '2 columns, custom button', 'code' => '[service_grid columns="2" button_text="Get Quote"]'],
                ['label' => '6 columns, no button', 'code' => '[service_grid columns="6" button="0"]'],
                ['label' => 'Uniform images', 'code' => '[service_grid image_crop="1" image_height="250"]'],
                ['label' => 'Featured first layout', 'code' => '[service_grid featured_first="1"]']
            ],
            'tips' => [
                'Use columns="3" for most professional service pages',
                'Set subtext="tagline" to use AI-generated taglines',
                'image_crop="1" creates uniform, Pinterest-style grids',
                'featured_first="1" makes the first service stand out',
                'Customize button_text for better conversion ("Get Started", "Learn More", etc.)'
            ]
        ],

        [
            'name' => 'service_area_grid',
            'category' => 'services',
            'description' => 'Displays a grid of service area pages with customizable layout and styling.',
            'basic_usage' => '[service_area_grid]',
            'attributes' => [
                'columns' => [
                    'default' => '3',
                    'description' => 'Number of columns: 2, 3, 4, or 6'
                ],
                'show_excerpt' => [
                    'default' => '1',
                    'description' => 'Show/hide excerpts'
                ],
                'button_text' => [
                    'default' => 'View Area',
                    'description' => 'Button text'
                ]
            ],
            'examples' => [
                ['label' => 'Default 3 columns', 'code' => '[service_area_grid]'],
                ['label' => '4 columns, custom button', 'code' => '[service_area_grid columns="4" button_text="Explore"]']
            ],
            'tips' => [
                'Great for service area landing pages',
                'Automatically pulls from Service Area CPT'
            ]
        ],

        [
            'name' => 'service_area_lists',
            'category' => 'services',
            'description' => 'Displays service areas as organized lists, grouped by parent/region.',
            'basic_usage' => '[service_area_lists]',
            'attributes' => [
                'layout' => [
                    'default' => 'grouped',
                    'description' => 'List style: grouped, flat, columns'
                ],
                'show_count' => [
                    'default' => '0',
                    'description' => 'Show number of areas per group'
                ]
            ],
            'examples' => [
                ['label' => 'Grouped by parent', 'code' => '[service_area_lists]'],
                ['label' => 'Flat list', 'code' => '[service_area_lists layout="flat"]']
            ],
            'tips' => [
                'Excellent for footer service area links',
                'layout="columns" creates multi-column layout'
            ]
        ],

        [
            'name' => 'service_area_children',
            'category' => 'services',
            'description' => 'Shows child service areas of the current page.',
            'basic_usage' => '[service_area_children]',
            'attributes' => [
                'parent_id' => [
                    'default' => '0',
                    'description' => 'Parent page ID (0 = current page)'
                ],
                'style' => [
                    'default' => 'list',
                    'description' => 'Display as: list, grid, or inline'
                ]
            ],
            'examples' => [
                ['label' => 'Child areas of current page', 'code' => '[service_area_children]'],
                ['label' => 'Grid layout', 'code' => '[service_area_children style="grid"]']
            ],
            'tips' => [
                'Use on county pages to show cities',
                'style="inline" creates comma-separated list'
            ]
        ],

        // ============================================================
        // CONTENT DISPLAY SHORTCODES
        // ============================================================

        [
            'name' => 'about_the_area',
            'category' => 'content',
            'description' => 'Displays AI-generated or custom "About the Area" content for service area pages.',
            'basic_usage' => '[about_the_area]',
            'attributes' => [
                'post_id' => [
                    'default' => '0',
                    'description' => 'Specific post ID (0 = current post)'
                ],
                'field' => [
                    'default' => 'about_area',
                    'description' => 'ACF field name containing content'
                ],
                'fallback' => [
                    'default' => '',
                    'description' => 'Default text if no content'
                ]
            ],
            'examples' => [
                ['label' => 'Basic usage', 'code' => '[about_the_area]'],
                ['label' => 'Custom field', 'code' => '[about_the_area field="area_description"]']
            ],
            'tips' => [
                'Content can be AI-generated via AI → Geo Content tab',
                'Great for adding local context to service pages'
            ]
        ],

        [
            'name' => 'custom_blog_cards',
            'category' => 'content',
            'description' => 'Displays blog posts in a modern card grid layout with images and excerpts.',
            'basic_usage' => '[custom_blog_cards]',
            'attributes' => [
                'posts_per_page' => [
                    'default' => '6',
                    'description' => 'Number of posts to display'
                ],
                'columns' => [
                    'default' => '3',
                    'description' => 'Number of columns: 2, 3, or 4'
                ],
                'category' => [
                    'default' => '',
                    'description' => 'Filter by category slug'
                ],
                'orderby' => [
                    'default' => 'date',
                    'description' => 'Sort by: date, title, rand'
                ]
            ],
            'examples' => [
                ['label' => 'Latest 6 posts', 'code' => '[custom_blog_cards]'],
                ['label' => '9 posts, 3 columns', 'code' => '[custom_blog_cards posts_per_page="9" columns="3"]'],
                ['label' => 'Specific category', 'code' => '[custom_blog_cards category="hvac-tips"]']
            ],
            'tips' => [
                'Perfect for blog archive pages',
                'Use category filter for topic-specific grids'
            ]
        ],

        [
            'name' => 'page_title',
            'category' => 'content',
            'description' => 'Outputs the current page title with optional wrapper elements.',
            'basic_usage' => '[page_title]',
            'attributes' => [
                'tag' => [
                    'default' => 'h1',
                    'description' => 'HTML tag: h1, h2, h3, div, span'
                ],
                'class' => [
                    'default' => '',
                    'description' => 'CSS classes to add'
                ]
            ],
            'examples' => [
                ['label' => 'Default H1', 'code' => '[page_title]'],
                ['label' => 'H2 with class', 'code' => '[page_title tag="h2" class="section-title"]']
            ],
            'tips' => [
                'Useful in page builders for dynamic titles',
                'Pairs well with location shortcodes'
            ]
        ],

        [
            'name' => 'post_author',
            'category' => 'content',
            'description' => 'Displays the post author name with optional bio and avatar.',
            'basic_usage' => '[post_author]',
            'attributes' => [
                'show_bio' => [
                    'default' => '0',
                    'description' => 'Include author bio: 1 or 0'
                ],
                'show_avatar' => [
                    'default' => '0',
                    'description' => 'Include avatar image: 1 or 0'
                ],
                'avatar_size' => [
                    'default' => '96',
                    'description' => 'Avatar size in pixels'
                ]
            ],
            'examples' => [
                ['label' => 'Author name only', 'code' => '[post_author]'],
                ['label' => 'With bio and avatar', 'code' => '[post_author show_bio="1" show_avatar="1"]']
            ],
            'tips' => [
                'Great for blog post templates',
                'show_bio pulls from WordPress user profile'
            ]
        ],

        // ============================================================
        // SCHEMA & SEO SHORTCODES
        // ============================================================

        [
            'name' => 'faq_schema_accordion',
            'category' => 'schema',
            'description' => 'Displays FAQs in an accordion with automatic FAQ schema markup for Google rich results.',
            'basic_usage' => '[faq_schema_accordion]',
            'attributes' => [
                'post_id' => [
                    'default' => '0',
                    'description' => 'Post ID (0 = current post)'
                ],
                'field' => [
                    'default' => 'myls_faqs',
                    'description' => 'ACF field name containing FAQs'
                ],
                'style' => [
                    'default' => 'accordion',
                    'description' => 'Display style: accordion or list'
                ],
                'open_first' => [
                    'default' => '0',
                    'description' => 'Auto-open first item: 1 or 0'
                ]
            ],
            'examples' => [
                ['label' => 'Basic accordion', 'code' => '[faq_schema_accordion]'],
                ['label' => 'First item open', 'code' => '[faq_schema_accordion open_first="1"]'],
                ['label' => 'List style', 'code' => '[faq_schema_accordion style="list"]']
            ],
            'tips' => [
                'Automatically adds FAQ schema for Google',
                'FAQs can be AI-generated via AI → FAQs tab',
                'Accordion is Bootstrap-powered and fully responsive'
            ]
        ],

        // ============================================================
        // SOCIAL & SHARING SHORTCODES
        // ============================================================

        [
            'name' => 'social_sharing',
            'category' => 'social',
            'description' => 'Adds social sharing buttons for Facebook, Twitter, LinkedIn, and email.',
            'basic_usage' => '[social_sharing]',
            'attributes' => [
                'platforms' => [
                    'default' => 'facebook,twitter,linkedin,email',
                    'description' => 'Comma-separated list of platforms'
                ],
                'style' => [
                    'default' => 'icons',
                    'description' => 'Display as: icons, buttons, or text'
                ],
                'size' => [
                    'default' => 'medium',
                    'description' => 'Icon size: small, medium, large'
                ]
            ],
            'examples' => [
                ['label' => 'All platforms', 'code' => '[social_sharing]'],
                ['label' => 'Facebook and Twitter only', 'code' => '[social_sharing platforms="facebook,twitter"]'],
                ['label' => 'Button style', 'code' => '[social_sharing style="buttons"]']
            ],
            'tips' => [
                'Great for blog posts and service pages',
                'Icons are Font Awesome by default'
            ]
        ],

        // ============================================================
        // UTILITY & TOOLS SHORTCODES
        // ============================================================

        [
            'name' => 'map_embed',
            'category' => 'utility',
            'description' => 'Embeds a Google Map with custom location marker.',
            'basic_usage' => '[map_embed]',
            'attributes' => [
                'address' => [
                    'default' => '',
                    'description' => 'Full address to display'
                ],
                'zoom' => [
                    'default' => '14',
                    'description' => 'Map zoom level: 1-20'
                ],
                'height' => [
                    'default' => '400',
                    'description' => 'Map height in pixels'
                ],
                'width' => [
                    'default' => '100%',
                    'description' => 'Map width (px or %)'
                ]
            ],
            'examples' => [
                ['label' => 'Basic map', 'code' => '[map_embed address="123 Main St, Tampa, FL"]'],
                ['label' => 'Custom size', 'code' => '[map_embed address="123 Main St" height="500" zoom="16"]']
            ],
            'tips' => [
                'Requires Google Maps API key in settings',
                'Address is geocoded automatically'
            ]
        ],

        [
            'name' => 'ajax_search',
            'category' => 'utility',
            'description' => 'Adds a live AJAX-powered search box with instant results.',
            'basic_usage' => '[ajax_search]',
            'attributes' => [
                'post_types' => [
                    'default' => 'post,page',
                    'description' => 'Post types to search (comma-separated)'
                ],
                'placeholder' => [
                    'default' => 'Search...',
                    'description' => 'Input placeholder text'
                ],
                'results_count' => [
                    'default' => '5',
                    'description' => 'Max results to show'
                ]
            ],
            'examples' => [
                ['label' => 'Search posts and pages', 'code' => '[ajax_search]'],
                ['label' => 'Search services only', 'code' => '[ajax_search post_types="service" placeholder="Search services..."]']
            ],
            'tips' => [
                'Results appear as you type',
                'Mobile-friendly dropdown design'
            ]
        ],

        [
            'name' => 'gmb_address',
            'category' => 'utility',
            'description' => 'Displays formatted business address from Google My Business / Organization settings.',
            'basic_usage' => '[gmb_address]',
            'attributes' => [
                'format' => [
                    'default' => 'full',
                    'description' => 'Address format: full, street, city, state, zip'
                ],
                'link' => [
                    'default' => '0',
                    'description' => 'Link to Google Maps: 1 or 0'
                ]
            ],
            'examples' => [
                ['label' => 'Full address', 'code' => '[gmb_address]'],
                ['label' => 'With Google Maps link', 'code' => '[gmb_address link="1"]'],
                ['label' => 'City only', 'code' => '[gmb_address format="city"]']
            ],
            'tips' => [
                'Pulls from Schema → Organization settings',
                'link="1" creates clickable directions'
            ]
        ],

        [
            'name' => 'yoast_title',
            'category' => 'utility',
            'description' => 'Outputs the Yoast SEO title or page title as fallback.',
            'basic_usage' => '[yoast_title]',
            'attributes' => [
                'tag' => [
                    'default' => 'h1',
                    'description' => 'HTML wrapper tag'
                ]
            ],
            'examples' => [
                ['label' => 'Default H1', 'code' => '[yoast_title]'],
                ['label' => 'As H2', 'code' => '[yoast_title tag="h2"]']
            ],
            'tips' => [
                'Useful for matching on-page title to meta title',
                'Falls back to regular title if Yoast not active'
            ]
        ],

        [
            'name' => 'category_list',
            'category' => 'utility',
            'description' => 'Displays a formatted list of post categories.',
            'basic_usage' => '[category_list]',
            'attributes' => [
                'separator' => [
                    'default' => ', ',
                    'description' => 'Text between categories'
                ],
                'show_count' => [
                    'default' => '0',
                    'description' => 'Show post count: 1 or 0'
                ]
            ],
            'examples' => [
                ['label' => 'Comma-separated', 'code' => '[category_list]'],
                ['label' => 'With counts', 'code' => '[category_list show_count="1"]']
            ],
            'tips' => [
                'Great for blog sidebars',
                'Automatically links to category archives'
            ]
        ]

    ];
}
