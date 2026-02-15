<?php
/**
 * My Local SEO – Interactive Shortcodes Documentation
 * File: admin/docs/shortcodes-interactive.php
 * 
 * Features:
 * - Bootstrap 5 cards layout
 * - Live search/filter
 * - Category filtering
 * - Copy to clipboard
 * - Expandable examples
 */

if (!defined('ABSPATH')) exit;

// Get all shortcode documentation data
$shortcodes_data = mlseo_get_all_shortcode_docs();

// Group by category
$categories = [
    'location' => 'Location & Geography',
    'services' => 'Services & Service Areas',
    'content' => 'Content Display',
    'schema' => 'Schema & SEO',
    'social' => 'Social & Sharing',
    'utility' => 'Utility & Tools'
];
?>

<style>
.shortcode-cards-wrapper {
    margin-top: 20px;
}

.shortcode-search-bar {
    max-width: 600px;
    margin: 20px auto;
}

.shortcode-filters {
    margin: 20px 0;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.shortcode-card {
    margin-bottom: 20px;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.shortcode-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-left-color: #2271b1;
}

.shortcode-card.cat-location { border-left-color: #4CAF50; }
.shortcode-card.cat-services { border-left-color: #2196F3; }
.shortcode-card.cat-content { border-left-color: #FF9800; }
.shortcode-card.cat-schema { border-left-color: #9C27B0; }
.shortcode-card.cat-social { border-left-color: #00BCD4; }
.shortcode-card.cat-utility { border-left-color: #607D8B; }

.shortcode-name {
    font-family: 'Courier New', monospace;
    font-size: 18px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 8px;
}

.shortcode-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-right: 8px;
}

.badge-location { background: #4CAF50; color: white; }
.badge-services { background: #2196F3; color: white; }
.badge-content { background: #FF9800; color: white; }
.badge-schema { background: #9C27B0; color: white; }
.badge-social { background: #00BCD4; color: white; }
.badge-utility { background: #607D8B; color: white; }

.copy-btn {
    font-size: 12px;
    padding: 2px 8px;
}

.attribute-table {
    font-size: 13px;
}

.attribute-table td {
    padding: 6px;
    vertical-align: top;
}

.attribute-name {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: #d63638;
}

.attribute-default {
    font-family: 'Courier New', monospace;
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
}

.example-block {
    background: #f6f7f7;
    border-left: 3px solid #2271b1;
    padding: 12px;
    margin: 10px 0;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.no-results {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.stats-bar {
    background: #f0f6fc;
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.collapse-toggle {
    cursor: pointer;
    color: #2271b1;
    font-size: 13px;
    margin-top: 10px;
}

.collapse-toggle:hover {
    text-decoration: underline;
}
</style>

<div class="shortcode-cards-wrapper">
    <!-- Search Bar -->
    <div class="shortcode-search-bar">
        <input 
            type="text" 
            id="shortcode-search" 
            class="form-control form-control-lg" 
            placeholder="🔍 Search shortcodes by name, description, or attribute..."
            autocomplete="off"
        >
    </div>

    <!-- Filters -->
    <div class="shortcode-filters">
        <strong>Category:</strong>
        <button class="btn btn-sm btn-outline-secondary filter-btn active" data-category="all">
            All (<?php echo count($shortcodes_data); ?>)
        </button>
        <?php foreach ($categories as $cat_key => $cat_label): 
            $count = count(array_filter($shortcodes_data, fn($sc) => $sc['category'] === $cat_key));
            if ($count > 0):
        ?>
            <button class="btn btn-sm btn-outline-secondary filter-btn" data-category="<?php echo $cat_key; ?>">
                <?php echo $cat_label; ?> (<?php echo $count; ?>)
            </button>
        <?php 
            endif;
        endforeach; 
        ?>
        
        <div style="margin-left: auto;">
            <button class="btn btn-sm btn-outline-primary" id="expand-all">Expand All</button>
            <button class="btn btn-sm btn-outline-primary" id="collapse-all">Collapse All</button>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div>
            <strong>Total Shortcodes:</strong> <span id="total-count"><?php echo count($shortcodes_data); ?></span> |
            <strong>Showing:</strong> <span id="visible-count"><?php echo count($shortcodes_data); ?></span>
        </div>
        <div>
            <button class="btn btn-sm btn-primary" id="export-docs">
                <i class="dashicons dashicons-download"></i> Export Documentation
            </button>
        </div>
    </div>

    <!-- Shortcode Cards Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-3" id="shortcodes-container">
        <?php foreach ($shortcodes_data as $shortcode): ?>
            <div class="shortcode-item" 
                 data-category="<?php echo esc_attr($shortcode['category']); ?>"
                 data-search="<?php echo esc_attr(strtolower($shortcode['name'] . ' ' . $shortcode['description'] . ' ' . implode(' ', array_keys($shortcode['attributes'])))); ?>">
                
                <div class="card shortcode-card cat-<?php echo esc_attr($shortcode['category']); ?>">
                    <div class="card-body">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="shortcode-name"><?php echo esc_html($shortcode['name']); ?></div>
                            <button class="btn btn-sm btn-outline-primary copy-btn" 
                                    data-shortcode="<?php echo esc_attr($shortcode['name']); ?>"
                                    title="Copy to clipboard">
                                📋 Copy
                            </button>
                        </div>

                        <!-- Category Badge -->
                        <div class="mb-2">
                            <span class="shortcode-badge badge-<?php echo esc_attr($shortcode['category']); ?>">
                                <?php echo esc_html($categories[$shortcode['category']] ?? 'Other'); ?>
                            </span>
                        </div>

                        <!-- Description -->
                        <p class="card-text"><?php echo esc_html($shortcode['description']); ?></p>

                        <!-- Basic Usage -->
                        <div class="example-block">
                            <?php echo esc_html($shortcode['basic_usage']); ?>
                        </div>

                        <!-- Expandable Details -->
                        <div class="collapse" id="details-<?php echo esc_attr(sanitize_title($shortcode['name'])); ?>">
                            
                            <?php if (!empty($shortcode['attributes'])): ?>
                                <h6 class="mt-3 mb-2"><strong>Attributes:</strong></h6>
                                <table class="table table-sm table-bordered attribute-table">
                                    <thead>
                                        <tr>
                                            <th width="30%">Attribute</th>
                                            <th width="25%">Default</th>
                                            <th width="45%">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($shortcode['attributes'] as $attr => $info): ?>
                                            <tr>
                                                <td class="attribute-name"><?php echo esc_html($attr); ?></td>
                                                <td><span class="attribute-default"><?php echo esc_html($info['default'] ?? '—'); ?></span></td>
                                                <td><?php echo esc_html($info['description'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                            <?php if (!empty($shortcode['examples'])): ?>
                                <h6 class="mt-3 mb-2"><strong>Examples:</strong></h6>
                                <?php foreach ($shortcode['examples'] as $example): ?>
                                    <div class="mb-2">
                                        <small class="text-muted"><?php echo esc_html($example['label']); ?></small>
                                        <div class="example-block">
                                            <?php echo esc_html($example['code']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (!empty($shortcode['tips'])): ?>
                                <div class="alert alert-info mt-3">
                                    <strong>💡 Tips:</strong>
                                    <ul class="mb-0">
                                        <?php foreach ($shortcode['tips'] as $tip): ?>
                                            <li><?php echo esc_html($tip); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Show/Hide Details Toggle -->
                        <div class="collapse-toggle" 
                             data-bs-toggle="collapse" 
                             data-bs-target="#details-<?php echo esc_attr(sanitize_title($shortcode['name'])); ?>">
                            <span class="show-text">▼ Show Details</span>
                            <span class="hide-text" style="display:none;">▲ Hide Details</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- No Results Message -->
    <div class="no-results" id="no-results" style="display:none;">
        <h3>No shortcodes found</h3>
        <p>Try adjusting your search or filter criteria</p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const $search = $('#shortcode-search');
    const $filterBtns = $('.filter-btn');
    const $items = $('.shortcode-item');
    const $container = $('#shortcodes-container');
    const $noResults = $('#no-results');
    const $visibleCount = $('#visible-count');

    let activeCategory = 'all';
    let searchQuery = '';

    // Filter function
    function filterItems() {
        let visibleCount = 0;

        $items.each(function() {
            const $item = $(this);
            const category = $item.data('category');
            const searchText = $item.data('search');

            const categoryMatch = activeCategory === 'all' || category === activeCategory;
            const searchMatch = searchQuery === '' || searchText.includes(searchQuery);

            if (categoryMatch && searchMatch) {
                $item.show();
                visibleCount++;
            } else {
                $item.hide();
            }
        });

        $visibleCount.text(visibleCount);
        
        if (visibleCount === 0) {
            $container.hide();
            $noResults.show();
        } else {
            $container.show();
            $noResults.hide();
        }
    }

    // Search
    $search.on('input', function() {
        searchQuery = $(this).val().toLowerCase();
        filterItems();
    });

    // Category filter
    $filterBtns.on('click', function() {
        $filterBtns.removeClass('active');
        $(this).addClass('active');
        activeCategory = $(this).data('category');
        filterItems();
    });

    // Copy to clipboard
    $(document).on('click', '.copy-btn', function() {
        const shortcode = '[' + $(this).data('shortcode') + ']';
        const $btn = $(this);

        navigator.clipboard.writeText(shortcode).then(function() {
            const originalText = $btn.text();
            $btn.text('✓ Copied!').removeClass('btn-outline-primary').addClass('btn-success');
            setTimeout(function() {
                $btn.text(originalText).removeClass('btn-success').addClass('btn-outline-primary');
            }, 2000);
        });
    });

    // Collapse toggle text
    $(document).on('show.bs.collapse', '.collapse', function() {
        const $toggle = $(this).prev('.collapse-toggle');
        $toggle.find('.show-text').hide();
        $toggle.find('.hide-text').show();
    });

    $(document).on('hide.bs.collapse', '.collapse', function() {
        const $toggle = $(this).prev('.collapse-toggle');
        $toggle.find('.show-text').show();
        $toggle.find('.hide-text').hide();
    });

    // Expand/Collapse All
    $('#expand-all').on('click', function() {
        $('.shortcode-item:visible .collapse').collapse('show');
    });

    $('#collapse-all').on('click', function() {
        $('.collapse').collapse('hide');
    });

    <!-- Export documentation -->
    $('#export-docs').on('click', function() {
        // Create a form and submit with nonce
        const form = $('<form>', {
            method: 'POST',
            action: '<?php echo admin_url('admin-post.php'); ?>'
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'mlseo_docs_export_pdf'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: '_wpnonce',
            value: '<?php echo wp_create_nonce('mlseo_docs_export_pdf'); ?>'
        }));
        
        form.appendTo('body').submit().remove();
    });
});
</script>
<?php

/**
 * Get all shortcode documentation data
 * This function compiles all shortcode information
 */
function mlseo_get_all_shortcode_docs() {
    $shortcodes = [];
    
    // We'll populate this with comprehensive data for each shortcode
    // For now, returning the structure - we'll fill in the complete data next
    
    return mlseo_compile_shortcode_documentation();
}
