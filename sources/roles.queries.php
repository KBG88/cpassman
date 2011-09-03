<?php
/**
 * @file 		roles.queries.php
 * @author		Nils Laumaillé
 * @version 	2.0
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	CC BY-ND (http://creativecommons.org/licenses/by-nd/3.0/legalcode)
 * @link		http://cpassman.org
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');

include('../includes/language/'.$_SESSION['user_language'].'.php');
include('../includes/settings.php');
header("Content-type: text/html; charset==utf-8");

//Connect to mysql server
require_once("class.database.php");
$db = new Database($server, $user, $pass, $database, $pre);
$db->connect();

// Construction de la requ?te en fonction du type de valeur
if ( !empty($_POST['type']) ){
    switch($_POST['type'])
    {
        #CASE adding a new role
        case "add_new_role":
            $db->query("INSERT INTO ".$pre."roles_title SET title = '".mysql_real_escape_string(stripslashes(($_POST['name'])))."'");
            //Actualize the variable
            $_SESSION['nb_roles'] ++;
            //reload page
            echo 'window.location.href = "index.php?page=manage_roles";';
        break;

        #-------------------------------------------
        #CASE delete a role
        case "delete_role":
            $db->query("DELETE FROM ".$pre."roles_title WHERE id = ".$_POST['id']);
        	$db->query("DELETE FROM ".$pre."roles_values WHERE role_id = ".$_POST['id']);
            //Actualize the variable
            $_SESSION['nb_roles'] --;
            //reload page
            echo 'window.location.href = "index.php?page=manage_roles";';
        break;

        #-------------------------------------------
        #CASE editing a role
        case "edit_role":
            $db->query_update(
                "roles_title",
                array(
                    'title' => mysql_real_escape_string(stripslashes(($_POST['title'])))
                ),
                'id = '.$_POST['id']
            );
            //reload matrix
            echo 'var data = "type=rafraichir_matrice";httpRequest("sources/roles.queries.php",data);';
        	break;

       	/******************************************
       	*CASE editing a role
       	*/
        case "allow_pw_change_for_role":
        	$db->query_update(
	        	"roles_title",
	        	array(
	        	    'allow_pw_change' => $_POST['value']
	        	),
	        	'id = '.$_POST['id']
        	);
        	break;


        #-------------------------------------------
        #CASE refresh the matrix
        case "rafraichir_matrice":
            echo 'document.getElementById(\'matrice_droits\').innerHTML = "";';
            require_once ("NestedTree.class.php");
            $tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $tree = $tree->getDescendants();
            $texte = '<table><thead><tr><th>'.$txt['group'].'s</th>';

			$gpes_ok = array();
            $gpes_nok = array();
            $tab_fonctions = array();
        	$arrRoles = array();

        	//count nb of roles
        	$roles_count = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."roles_title");
        	if($roles_count > 9){
        		if(!isset($_POST['start'])){
        			$start = 0;
        		}else{
        			$start = $_POST['start'];
        		}
        		$sql_limit = " LIMIT $start, 9";
        	}


        	//Display table header
            $rows = $db->fetch_all_array("
				SELECT id, title, allow_pw_change
				FROM ".$pre."roles_title
				ORDER BY title ASC".
            	$sql_limit);
            foreach( $rows as $reccord ){
            	if ($reccord['allow_pw_change'] == 1) {
            		$allow_pw_change = '&nbsp;<img id="img_apcfr_'.$reccord['id'].'" src=\'includes/images/ui-text-field-password-green.png\' onclick=\'allow_pw_change_for_role('.$reccord['id'].', 0)\' style=\'cursor:pointer;\' title=\''.$txt['role_cannot_modify_all_seen_items'].'\' \>';
            	}else{
            		$allow_pw_change = '&nbsp;<img id="img_apcfr_'.$reccord['id'].'" src=\'includes/images/ui-text-field-password-red.png\' onclick=\'allow_pw_change_for_role('.$reccord['id'].', 1)\' style=\'cursor:pointer;\' title=\''.$txt['role_can_modify_all_seen_items'].'\' \>';
            	}
            	$texte .= '<th style="font-size:10px;min-width:60px;" class="edit_role">'.$reccord['title'].'<br><img src=\'includes/images/ui-tab--pencil.png\' onclick=\'edit_this_role('.$reccord['id'].',"'.$reccord['title'].'")\' style=\'cursor:pointer;\' \>&nbsp;<img src=\'includes/images/ui-tab--minus.png\' onclick=\'delete_this_role('.$reccord['id'].',"'.$reccord['title'].'")\' style=\'cursor:pointer;\' \>' .$allow_pw_change. '</th>';

            	array_push($arrRoles, $reccord['id']);
            }
            $texte .= '</tr></thead><tbody>';


        	//Display each folder with associated rights by role
        	$i=0;
        	foreach($tree as $node){
        		if ( in_array($node->id, $_SESSION['groupes_visibles']) && !in_array($node->id, $_SESSION['personal_visible_groups']) ) {
        			$ident="";
        			for($a=1;$a<$node->nlevel;$a++) $ident .= "&nbsp;&nbsp;";

        			//display 1st cell of the line
        			$texte .= '<tr><td style="font-size:10px; font-family:arial;">'.$ident.$node->title.'</td>';

        			foreach($arrRoles as $role){
        				//check if this role has access or not
        				// if not then color is red; if yes then color is green
        				$count = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."roles_values WHERE folder_id = ".$node->id." AND role_id = ".$role);
        				if ($count[0] > 0) {
        					$couleur = '#008000';
        					$allowed = 1;
        				}else{
        					$couleur = '#FF0000';
        					$allowed = 0;
        				}
        				$texte .= '<td align="center" style="background-color:'.$couleur.'" onclick="tm_change_role('.$role.','.$node->id.','.$i.','.$allowed.')" id="tm_cell_'.$i.'"></td>';
        				$i++;
        			}
        			$texte .= '</tr>';
        		}
        	}
            $texte .= '</tbody></table>';
            echo 'document.getElementById(\'matrice_droits\').innerHTML = "'.addslashes($texte).'";';
            echo '$("#div_loading").hide()';  //hide loading div
        break;

        #-------------------------------------------
        #CASE change right for a role on a folder via the TM
        case "change_role_via_tm";
        	//get full tree dependencies
        	require_once ("NestedTree.class.php");
        	$tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
        	$tree = $tree->getDescendants($_POST['folder'],true);

        	if (isset($_POST['allowed']) AND $_POST['allowed'] == 1) {
        		//case where folder was allowed but not any more
        		foreach($tree as $node){
        			//Store in DB
        			$db->query_delete(
	        			'roles_values',
	        			array(
	        			    'folder_id' => $node->id,
	        			    'role_id' => $_POST['role']
	        			)
        			);
        		}
        	}else if ($_POST['allowed'] == 0){
        		//case where folder was not allowed but allowed now
        		foreach($tree as $node){
        			//Store in DB
        			$db->query_insert(
	        			'roles_values',
	        			array(
	        			    'folder_id' => $node->id,
	        			    'role_id' => $_POST['role']
	        			)
        			);
        		}
        	}

            echo 'httpRequest("sources/roles.queries.php","type=rafraichir_matrice");';

            echo '$("#div_loading").hide();';
        break;
    }
}else if ( !empty($_POST['edit_fonction']) ){
    $id = explode('_',$_POST['id']);
    //Update DB
    $db->query_update(
        'roles_title',
        array(
            'title' => mysql_real_escape_string(stripslashes(utf8_decode($_POST['edit_fonction'])))
        ),
        "id = ".$id[1]
    );
    //Show value
    echo $_POST['edit_fonction'];
}
?>