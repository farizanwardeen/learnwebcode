<?php 
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('BBoss_Global_Search_Groups')):

	/**
	 *
	 * BuddyPress Global Search  - search groups
	 * **************************************
	 *
	 *
	 */
	class BBoss_Global_Search_Groups extends BBoss_Global_Search_Type {
		private $type = 'groups';
		
		/**
		 * Insures that only one instance of Class exists in memory at any
		 * one time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0.0
		 *
		 * @return object BBoss_Global_Search_Groups
		 */
		public static function instance() {
			// Store the instance locally to avoid private static replication
			static $instance = null;

			// Only run these methods if they haven't been run previously
			if (null === $instance) {
				$instance = new BBoss_Global_Search_Groups();
			}

			// Always return the instance
			return $instance;
		}

		/**
		 * A dummy constructor to prevent this class from being loaded more than once.
		 *
		 * @since 1.0.0
		 */
		private function __construct() { /* Do nothing here */
		}

		public function sql( $search_term, $only_totalrow_count=false ){
			/* an example UNION query :- 
			-----------------------------------------------------
			(
				SELECT 
					DISTINCT g.id, 'groups' as type, g.name LIKE '%ho%' AS relevance, gm2.meta_value as entry_date 
				FROM 
					wp_bp_groups_groupmeta gm1, wp_bp_groups_groupmeta gm2, wp_bp_groups g 
				WHERE 
					1=1 
					AND g.id = gm1.group_id 
					AND g.id = gm2.group_id 
					AND gm2.meta_key = 'last_activity' 
					AND gm1.meta_key = 'total_member_count' 
					AND ( g.name LIKE '%ho%' OR g.description LIKE '%ho%' )
			)
			----------------------------------------------------
			*/
			global $wpdb, $bp;
			$query_placeholder = array();
			
			$sql = " SELECT ";
			
			if( $only_totalrow_count ){
				$sql .= " COUNT( DISTINCT g.id ) ";
			} else {
				$sql .= " DISTINCT g.id, 'groups' as type, g.name LIKE '%%%s%%' AS relevance, gm2.meta_value as entry_date ";
				$query_placeholder[] = $search_term;
			}
						
			$sql .= " FROM 
						{$bp->groups->table_name_groupmeta} gm1, {$bp->groups->table_name_groupmeta} gm2, {$bp->groups->table_name} g 
					WHERE 
						1=1 
						AND g.id = gm1.group_id 
						AND g.id = gm2.group_id 
						AND gm2.meta_key = 'last_activity' 
						AND gm1.meta_key = 'total_member_count' 
						AND ( g.name LIKE '%%%s%%' OR g.description LIKE '%%%s%%' )
				";
			$query_placeholder[] = $search_term;
			$query_placeholder[] = $search_term;
                        
                        //String Search
                        
                        if (function_exists('bp_bpla') && 'yes' == bp_bpla()->option('enable-for-groups') ) {
                                
                                $split_search_term = explode(' ', $search_term);

                                $sql .= "OR g.id IN ( SELECT group_id FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'bbgs_group_search_string' AND ";

                                foreach ( $split_search_term as $k => $sterm ) {

                                    if ( $k == 0 ) {
                                        $sql .= "meta_value LIKE '%%%s%%'";
                                        $query_placeholder[] = $sterm;
                                    } else {
                                        $sql .= "AND meta_value LIKE '%%%s%%'";
                                        $query_placeholder[] = $sterm;
                                    }

                                }
                                $sql .= " ) ";
                        
                        }
                            
			
			/**
			 * Properly handle hidden groups.
			 * For guest users - exclude all hidden groups.
			 * For members - include only those hidden groups where current user is a member.
			 * For admins - include all hidden groups ( do nothing extra ).
			 * @since 1.1.0
			 */
			if( is_user_logged_in() ){
				if( !current_user_can( 'level_10' ) ){
					//get all hidden groups where i am a member of
					$hidden_groups_sql = $wpdb->prepare( "SELECT DISTINCT gm.group_id FROM {$bp->groups->table_name_members} gm JOIN {$bp->groups->table_name} g ON gm.group_id = g.id WHERE gm.user_id = %d AND gm.is_confirmed = 1 AND gm.is_banned = 0 AND g.status='hidden' ", bp_loggedin_user_id() );
					$hidden_groups_ids = $wpdb->get_col( $hidden_groups_sql );
					if( empty( $hidden_groups_ids ) ){
						$hidden_groups_ids = array( 99999999 );//arbitrarily large number
					}

					$hidden_groups_ids_csv = implode( ',', $hidden_groups_ids );

					//either gruops which are not hidden,
					//or if hidden, only those where i am a member.
					$sql .= " AND ( g.status != 'hidden' OR g.id IN ( {$hidden_groups_ids_csv} ) ) ";
				}
			} else {
				$sql .= " AND g.status != 'hidden' ";
			}
			
			$sql = $wpdb->prepare( $sql, $query_placeholder );
            
            return apply_filters( 
                'BBoss_Global_Search_Groups_sql', 
                $sql, 
                array( 
                    'search_term'           => $search_term,
                    'only_totalrow_count'   => $only_totalrow_count,
                ) 
            );
		}
		
		protected function generate_html( $template_type='' ){
			$group_ids = array();
			foreach( $this->search_results['items'] as $item_id=>$item_html ){
				$group_ids[] = $item_id;
			}

			//now we have all the posts
			//lets do a groups loop
			$args = array( 'include'=>$group_ids, 'per_page'=>count($group_ids) );
			if( is_user_logged_in() ){
				$args['show_hidden'] = true;
			}
			
			if( bp_has_groups( $args ) ){
				while ( bp_groups() ){
					bp_the_group();

					$result = array(
						'id'	=> bp_get_group_id(),
						'type'	=> $this->type,
						'title'	=> bp_get_group_name(),
						'html'	=> buddyboss_global_search_buffer_template_part( 'loop/group', $template_type, false ),
					);

					$this->search_results['items'][bp_get_group_id()] = $result;
				}

				//var_dump( $this->search_results['items'] );
			}
		}
	}

// End class BBoss_Global_Search_Groups

endif;
?>