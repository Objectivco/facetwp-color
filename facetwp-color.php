<?php
/*
Plugin Name: Objectiv FacetWP - Color
Plugin URI: https://facetwp.com/
Description: A FacetWP facet to filter products by color
Version: 1.2
Author: FacetWP, LLC, Objectiv
GitHub URI: objectiv/facetwp-color
*/

defined( 'ABSPATH' ) or exit;

function fwp_color_facet($facet_types)
{
    $facet_types['color'] = new FacetWP_Facet_Color();
    return $facet_types;
}
add_filter( 'facetwp_facet_types', 'fwp_color_facet' );


/**
 * The Color facet class
 */
class FacetWP_Facet_Color
{

    function __construct()
    {
        $this->label = __( 'Color', 'fwp' );
    }


    /**
     * Load the available choices
     */
    function load_values($params)
    {
        global $wpdb;

        $facet = $params['facet'];
        $where_clause = $params['where_clause'];

        // Orderby
        $orderby = 'counter DESC, f.facet_display_value ASC';

        // Sort by depth just in case
        $orderby = "f.depth, $orderby";

        // Limit
        $limit = ctype_digit( $facet['count'] ) ? $facet['count'] : 10;
        $orderby = apply_filters( 'facetwp_facet_orderby', $orderby, $facet );
        $where_clause = apply_filters( 'facetwp_facet_where', $where_clause, $facet );

        $sql = "
        SELECT f.facet_value, f.facet_display_value, f.term_id, f.parent_id, f.depth, COUNT(*) AS counter
        FROM {$wpdb->prefix}facetwp_index f
        WHERE f.facet_name = '{$facet['name']}' $where_clause
        GROUP BY f.facet_value
        ORDER BY $orderby
        LIMIT $limit";

        $output = $wpdb->get_results( $sql, ARRAY_A );

        foreach ($output as $key => $value) {
            $term_id = $value['term_id'];
            $hex = get_term_meta( $term_id, 'hex_code', true );

            if (! empty( $hex )) {
                $output[$key]['facet_display_value'] = $hex;
            }
        }

        return $output;
    }


    /**
     * Generate the facet HTML
     */
    function render($params)
    {

        $facet = $params['facet'];

        $output = '';
        $values = (array) $params['values'];
        $selected_values = (array) $params['selected_values'];

        foreach ($values as $result) {
            $selected = in_array( $result['facet_value'], $selected_values ) ? ' checked' : '';
            $selected .= ( 0 == $result['counter'] ) ? ' disabled' : '';
            $output .= '<div class="facetwp-color' . $selected . '" data-value="' . $result['facet_value'] . '" data-color="' . esc_attr( $result['facet_display_value'] ) . '"><span class="facetwp-color-circle"></span></div>';
        }

        return $output;
    }


    /**
     * Filter the query based on selected values
     */
    function filter_posts($params)
    {
        global $wpdb;

        $output = array();
        $facet = $params['facet'];
        $selected_values = $params['selected_values'];

        $sql = $wpdb->prepare( "SELECT DISTINCT post_id
            FROM {$wpdb->prefix}facetwp_index
            WHERE facet_name = %s",
            $facet['name']
        );

        foreach ($selected_values as $key => $value) {
            $results = $wpdb->get_col( $sql . " AND facet_value IN ('$value')" );
            $output = ( $key > 0 ) ? array_intersect( $output, $results ) : $results;

            if (empty( $output )) {
                break;
            }
        }

        return $output;
    }


    /**
     * Output any admin scripts
     */
    function admin_scripts()
    {
?>
<script>
(function($) {
    wp.hooks.addAction('facetwp/load/color', function($this, obj) {
        $this.find('.facet-source').val(obj.source);
        $this.find('.facet-count').val(obj.count);
    });

    wp.hooks.addFilter('facetwp/save/color', function($this, obj) {
        obj['source'] = $this.find('.facet-source').val();
        obj['count'] = $this.find('.facet-count').val();
        return obj;
    });


})(jQuery);
</script>
<?php
    }


    /**
     * Output any front-end scripts
     */
    function front_scripts()
    {
?>

<style type="text/css">
.facetwp-color {
    border-radius: 50%;
    cursor: pointer;
    display: inline-block;
    margin: 0 12px 12px 0;
    transition: all 0.3s ease-in-out;
}

.facetwp-color:nth-child(5n) {
    margin-right: 0;
}

.facetwp-color-circle {
    border-radius: 50%;
    border: 1px solid #eee;
    display: block;
    height: 40px;
    position: relative;
    width: 40px;
}

.facetwp-color-circle::after {
    border-radius: 50%;
    border: 1px solid #a1a1a1;
    content: "";
    display: block;
    height: 46px;
    left: 50%;
    position: absolute;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 46px;
    opacity: 0;
    pointer-events: none;
    transition: all 300ms ease;
}

.facetwp-color:hover .facetwp-color-circle::after {
    opacity: 1;
}

.facetwp-color.checked .facetwp-color-circle::after {
    opacity: 1;
}
</style>

<script>
(function($) {
    wp.hooks.addAction('facetwp/refresh/color', function($this, facet_name) {
        var selected_values = [];
        $this.find('.facetwp-color.checked').each(function() {
            selected_values.push($(this).attr('data-value'));
        });
        FWP.facets[facet_name] = selected_values;
    });

    wp.hooks.addAction('facetwp/ready', function() {
        $(document).on('click touchstart', '.facetwp-facet .facetwp-color:not(.disabled)', function(e) {
            if (true === e.handled) {
                return false;
            }
            e.handled = true;
            $(this).toggleClass('checked');
            var $facet = $(this).closest('.facetwp-facet');
            FWP.autoload();
        });
    });

    $(document).on('facetwp-loaded', function() {
        $('.facetwp-color').each(function() {
            $(this).find('.facetwp-color-circle').css('background-color', $(this).attr('data-color'));
        });
    });
})(jQuery);
</script>
<?php
    }


    /**
     * Output admin settings HTML
     */
    function settings_html()
    {
?>
        <tr>
            <td>
                <?php _e('Count', 'fwp'); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content"><?php _e( 'The maximum number of facet choices to show', 'fwp' ); ?></div>
                </div>
            </td>
            <td><input type="text" class="facet-count" value="10" /></td>
        </tr>
<?php
    }
}