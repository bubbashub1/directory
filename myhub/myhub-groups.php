<?php
/** BubbaHub My Hub group collections and recommendation engine. */
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_groups_assets' );
add_action( 'wp_ajax_bubbahub_myhub_groups', 'bubbahub_myhub_groups_ajax' );
add_action( 'init', 'bubbahub_myhub_groups_create_page' );
add_shortcode( 'bubbahub_myhub_groups', 'bubbahub_myhub_groups_shortcode' );
function bubbahub_myhub_groups_assets(){if(!is_user_logged_in())return;wp_register_style('bubbahub-myhub-groups',BUBBAHUB_MYHUB_URL.'myhub-groups.css',array(),BUBBAHUB_MYHUB_VERSION);wp_register_script('bubbahub-myhub-groups',BUBBAHUB_MYHUB_URL.'myhub-groups.js',array('jquery'),BUBBAHUB_MYHUB_VERSION,true);wp_localize_script('bubbahub-myhub-groups','BubbaHubMyHubGroups',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bubbahub_myhub_groups'),'pageUrl'=>home_url('/my-groups/')));}
function bubbahub_myhub_groups_create_page(){if(get_option('bubbahub_myhub_groups_page_created'))return;$page=get_page_by_path('my-groups');if(!$page){$id=wp_insert_post(array('post_title'=>'My Groups','post_name'=>'my-groups','post_content'=>'[bubbahub_myhub_groups]','post_status'=>'publish','post_type'=>'page'),true);if(!is_wp_error($id))update_option('bubbahub_myhub_groups_page_created',1,false);}else update_option('bubbahub_myhub_groups_page_created',1,false);}
function bubbahub_myhub_groups_clean_ids($ids){$ids=is_array($ids)?$ids:array();$ids=array_values(array_unique(array_filter(array_map('absint',$ids))));if(!$ids)return array();return array_map('absint',get_posts(array('post_type'=>'group','post_status'=>'publish','post__in'=>$ids,'posts_per_page'=>count($ids),'fields'=>'ids','orderby'=>'post__in','no_found_rows'=>true)));}
function bubbahub_myhub_groups_card($post_id){$title=get_the_title($post_id);$url=get_permalink($post_id);$image=function_exists('bubbahub_directory_image_url')?bubbahub_directory_image_url($post_id):get_the_post_thumbnail_url($post_id,'medium_large');$region=function_exists('bubbahub_directory_region')?bubbahub_directory_region($post_id):'';$age=function_exists('bubbahub_directory_get_field')?bubbahub_directory_get_field($post_id,'age_range'):get_post_meta($post_id,'age_range',true);$price=function_exists('bubbahub_directory_get_field')?bubbahub_directory_get_field($post_id,'price'):get_post_meta($post_id,'price',true);if(is_array($age))$age=implode(', ',$age);if(is_array($price))$price=implode(', ',$price);ob_start();?><article class="bh-myhub-group-card" data-group-id="<?php echo esc_attr($post_id); ?>"><a class="bh-myhub-group-image" href="<?php echo esc_url($url); ?>"><span><?php if($image):?><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy"><?php else:?>BubbaHub<?php endif;?></span></a><div class="bh-myhub-group-body"><h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title);?></a></h3><?php if($region):?><div class="bh-myhub-group-meta">⌖ <?php echo esc_html($region);?></div><?php endif;?><div class="bh-myhub-group-tags"><?php if($age):?><span><?php echo esc_html($age);?></span><?php endif;?><?php if($price):?><span><?php echo esc_html($price);?></span><?php endif;?></div><a class="bh-myhub-group-view" href="<?php echo esc_url($url);?>">View group <span>→</span></a></div></article><?php return ob_get_clean();}
function bubbahub_myhub_groups_user_preferences(){
    $uid=get_current_user_id();
    $read=function($names)use($uid){
        foreach($names as $name){
            $value=get_user_meta($uid,$name,true);
            if(function_exists('get_field')){
                $acf=get_field($name,'user_'.$uid,false);
                if($acf!==null&&$acf!==false&&$acf!=='')$value=$acf;
            }
            if($value!==null&&$value!==false&&$value!=='')return (array)$value;
        }
        return array();
    };
    $interests=$read(array('user_interests','interests'));
    $locations=$read(array('preferred_location','preferred_locations'));
    $interest_out=array();
    foreach($interests as $v){
        if(is_object($v)&&isset($v->slug))$v=$v->slug;
        if(is_array($v)&&isset($v['slug']))$v=$v['slug'];
        if(is_scalar($v)){$v=sanitize_title((string)$v);if($v!=='')$interest_out[]=$v;}
    }
    $location_out=array();
    foreach($locations as $v){
        if(is_object($v)&&isset($v->term_id))$v=$v->term_id;
        if(is_array($v)&&isset($v['term_id']))$v=$v['term_id'];
        if(is_scalar($v)){ $raw=(string)$v; if(absint($raw)) $location_out[]=absint($raw); else { $slug=sanitize_title($raw); if($slug!=='') $location_out[]=$slug; } }
    }
    return array(array_values(array_unique($interest_out)),array_values(array_unique($location_out)));
}
function bubbahub_myhub_groups_age_tokens($selected=array()){
    $children=get_posts(array('post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
    $selected=array_map('absint',(array)$selected);
    $tokens=array();
    foreach($children as $id){
        if($selected&&!in_array($id,$selected,true))continue;
        $status=function_exists('get_field')?get_field('child_status',$id):get_post_meta($id,'child_status',true);
        if($status==='expecting')continue;
        $dob=function_exists('get_field')?get_field('child_date_of_birth',$id):get_post_meta($id,'child_date_of_birth',true);
        if(!$dob)continue;
        $dob=sanitize_text_field($dob);
        $b=DateTime::createFromFormat('Y-m-d',$dob);
        if(!$b)$b=new DateTime($dob);
        $t=new DateTime('today');
        if($b>$t)continue;
        $d=$b->diff($t);
        $m=((int)$d->y*12)+(int)$d->m;
        $tokens[]=$m;
    }
    return array_values(array_unique($tokens));
}
function bubbahub_myhub_groups_age_range_matches($value,$child_months){
    if(empty($child_months)||$value===''||$value===null)return false;
    $values=is_array($value)?$value:array($value);
    $ranges=array(
        '0-3'=>array(0,3,'months'),
        '3-6'=>array(3,6,'months'),
        '6-9'=>array(6,9,'months'),
        '9-12'=>array(9,12,'months'),
        '1-3'=>array(12,36,'months'),
        '2-4'=>array(24,48,'months'),
        '3-5'=>array(36,60,'months'),
        '5-plus'=>array(60,null,'months'),
        'all'=>array(0,null,'months')
    );
    foreach($values as $raw){
        $key=sanitize_title((string)$raw);
        if(isset($ranges[$key])){
            foreach($child_months as $m){
                $m=(int)$m;
                $min=$ranges[$key][0];
                $max=$ranges[$key][1];
                if($m>=$min&&($max===null||$m<$max))return true;
            }
            continue;
        }
        $hay=strtolower(trim((string)$raw));
        if(strpos($hay,'pregnan')!==false||strpos($hay,'antenatal')!==false)continue;
        if(strpos($hay,'all ages')!==false||strpos($hay,'any age')!==false)return true;
        foreach($child_months as $m){
            if(preg_match('/(\\d+)\\s*(?:-|–|to)\\s*(\\d+)\\s*months?/i',$hay,$x)&&$m>=(int)$x[1]&&$m<(int)$x[2])return true;
            if(preg_match('/(\\d+)\\s*(?:-|–|to)\\s*(\\d+)\\s*years?/i',$hay,$x)&&$m>=(int)$x[1]*12&&$m<(int)$x[2]*12)return true;
            if(preg_match('/(\\d+)\\s*\\+\\s*(?:years?|yrs?)/i',$hay,$x)&&$m>=(int)$x[1]*12)return true;
            if(preg_match('/(\\d+)\\s*plus/i',$hay,$x)&&$m>=(int)$x[1]*12)return true;
        }
    }
    return false;
}
function bubbahub_myhub_groups_score($id,$interests,$locations,$ages){
    $score=0;
    $matched_location=false;
    if($locations){
        foreach(array('location','region') as $tax){
            if(!taxonomy_exists($tax))continue;
            $terms=get_the_terms($id,$tax);
            if(is_wp_error($terms)||!$terms)continue;
            foreach($terms as $t){
                if(in_array((int)$t->term_id,$locations,true)||in_array(sanitize_title($t->slug),array_map('sanitize_title',$locations),true)){
                    $matched_location=true;break 2;
                }
            }
        }
        if($matched_location)$score+=4;
    }
    $interest_match=false;
    $interest_terms=array();
    foreach(array('interest','category','activity_type') as $tax){
        if(!taxonomy_exists($tax))continue;
        $terms=get_the_terms($id,$tax);
        if(!is_wp_error($terms)&&$terms)foreach($terms as $t)$interest_terms[]=sanitize_title($t->slug.' '.$t->name);
    }
    $fields=array('interests','interest','activity_type','category');
    foreach($fields as $field){
        $v=function_exists('bubbahub_directory_get_field')?bubbahub_directory_get_field($id,$field,''):get_post_meta($id,$field,true);
        if($v!==''&&$v!==null)$interest_terms[]=sanitize_title(is_array($v)?implode(' ',array_map('strval',$v)):strval($v));
    }
    foreach($interests as $i){
        $needle=sanitize_title($i);
        foreach($interest_terms as $hay){
            if($needle!==''&&($needle===$hay||strpos($hay,$needle)!==false||strpos($needle,$hay)!==false)){$interest_match=true;break 2;}
        }
    }
    if($interest_match)$score+=3;
    $v=function_exists('bubbahub_directory_get_field')?bubbahub_directory_get_field($id,'age_range',bubbahub_directory_get_field($id,'Age_Range','')):get_post_meta($id,'age_range',get_post_meta($id,'Age_Range',true));
    if(bubbahub_myhub_groups_age_range_matches($v,$ages))$score+=3;
    return $score;
}
function bubbahub_myhub_groups_suggested_ids($exclude=array(),$selected=array()){
    list($interests,$locations)=bubbahub_myhub_groups_user_preferences();
    $ages=bubbahub_myhub_groups_age_tokens($selected);
    $q=new WP_Query(array('post_type'=>'group','post_status'=>'publish','posts_per_page'=>200,'post__not_in'=>array_map('absint',$exclude),'orderby'=>'date','order'=>'DESC','no_found_rows'=>true));
    $scored=array();
    while($q->have_posts()){
        $q->the_post();
        $id=get_the_ID();
        /* When child ages are available, Age_Range is a hard eligibility check.
         * Interests and preferred location then rank the age-appropriate groups. */
        if($ages){
            $age_value=function_exists('bubbahub_directory_get_field')?bubbahub_directory_get_field($id,'age_range',bubbahub_directory_get_field($id,'Age_Range','')):get_post_meta($id,'age_range',get_post_meta($id,'Age_Range',true));
            if(!bubbahub_myhub_groups_age_range_matches($age_value,$ages))continue;
        }
        $s=bubbahub_myhub_groups_score($id,$interests,$locations,$ages);
        if($s>0)$scored[$id]=$s;
    }
    wp_reset_postdata();
    arsort($scored,SORT_NUMERIC);
    return array_slice(array_values(array_unique(array_map('absint',array_keys($scored)))),0,24);
}
function bubbahub_myhub_groups_ajax(){if(!is_user_logged_in())wp_send_json_error(array('message'=>'Please log in.'),401);/* Read-only logged-in endpoint: do not require a cached page nonce. */$type=isset($_POST['type'])?sanitize_key(wp_unslash($_POST['type'])):'favourite';$ids=isset($_POST['ids'])?json_decode(wp_unslash($_POST['ids']),true):array();$ids=bubbahub_myhub_groups_clean_ids($ids);$favs=isset($_POST['favourites'])?json_decode(wp_unslash($_POST['favourites']),true):array();$visited=isset($_POST['visited'])?json_decode(wp_unslash($_POST['visited']),true):array();$recent=isset($_POST['recently_viewed'])?json_decode(wp_unslash($_POST['recently_viewed']),true):array();$selected=isset($_POST['selected_children'])?json_decode(wp_unslash($_POST['selected_children']),true):array();if($type==='suggested'){$exclude=array_merge($ids,bubbahub_myhub_groups_clean_ids($favs),bubbahub_myhub_groups_clean_ids($visited),bubbahub_myhub_groups_clean_ids($recent));$ids=bubbahub_myhub_groups_suggested_ids($exclude,$selected);}elseif($type==='recently_viewed')$ids=bubbahub_myhub_groups_clean_ids($recent);$html='';foreach(array_slice($ids,0,24) as $id)$html.=bubbahub_myhub_groups_card($id);wp_send_json_success(array('html'=>$html,'count'=>count($ids)));}
function bubbahub_myhub_groups_shortcode(){if(!is_user_logged_in())return '<div class="bh-myhub-login"><h2>My Groups</h2><p>Please log in to see your groups.</p></div>';wp_enqueue_style('bubbahub-myhub');wp_enqueue_style('bubbahub-myhub-groups');wp_enqueue_script('bubbahub-myhub-groups');$view=isset($_GET['group_view'])?sanitize_key(wp_unslash($_GET['group_view'])):'favourite';if(!in_array($view,array('recently_viewed','favourite','visited','suggested'),true))$view='favourite';$titles=array('recently_viewed'=>'Recently Viewed','favourite'=>'Favourite Groups','visited'=>'Visited Groups','suggested'=>'Suggested Groups');ob_start();?><div class="bh-myhub bh-myhub-groups-page" data-myhub-groups-page="<?php echo esc_attr($view);?>"><div class="bh-myhub-groups-page-head"><div><div class="bh-myhub-kicker">YOUR BUBBA HUB</div><h1><?php echo esc_html($titles[$view]);?></h1><p>Keep the local groups that matter to your family close at hand.</p></div><a class="bh-myhub-button secondary" href="<?php echo esc_url(home_url('/my-hub/'));?>">← Back to My Hub</a></div><div class="bh-myhub-groups-tabs"><?php foreach($titles as $key=>$label):?><a class="<?php echo $view===$key?'is-active':'';?>" href="<?php echo esc_url(add_query_arg('group_view',$key,home_url('/my-groups/')));?>"><?php echo esc_html($label);?></a><?php endforeach;?></div><div class="bh-myhub-groups-results" data-myhub-group-results data-group-view="<?php echo esc_attr($view);?>"><div class="bh-myhub-groups-loading">Loading your groups…</div></div></div><?php return ob_get_clean();}
