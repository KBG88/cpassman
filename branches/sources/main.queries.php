<?php
####################################################################################################
## File : main.queries.php
## Author : Nils Laumaill�
## Description : File contains queries for ajax
## 
## DON'T CHANGE !!!
## 
####################################################################################################

include('../includes/settings.php'); 
header("Content-type: text/html; charset=".$k['charset']);
session_start();
$k['langage'] = @$_SESSION['user_language'];    
require_once('../includes/language/'.$_SESSION['user_language'].'.php'); 
    

// Construction de la requ�te en fonction du type de valeur
switch($_POST['type'])
{
    case "change_pw":
        //v�rifier certaines valeurs
        $tmp = explode(';',$_SESSION['last_pw']);
        if ( in_array(md5($_POST['new_pw']),$tmp) ){
            echo 'document.getElementById(\'new_pw\').value = "";';
            echo 'document.getElementById(\'new_pw2\').value = "";';
            echo 'alert(\''.$txt['pw_used'].'\');';
        }else{
            //MAJ la liste des derniers mdps
            if ( sizeof($tmp) == 5 )
                unset($tmp[0]);
            else 
                array_push($tmp,md5($_POST['new_pw']));
            
            $_SESSION['last_pw'] = implode(';',$tmp);
            $_SESSION['last_pw_change'] = mktime(0,0,0,date('m'),date('d'),date('y'));
            $_SESSION['validite_pw'] = true;
            
            $sql="UPDATE ".$k['prefix']."users SET pw = '".md5($_POST['new_pw'])."', last_pw_change = '".mktime(0,0,0,date('m'),date('d'),date('y'))."', last_pw = '".implode(';',$tmp)."' WHERE id = ".$_SESSION['user_id'];
            mysql_query($sql) or die('Erreur SQL !<br>'.$sql.'<br>'.mysql_error());            
            
            echo 'document.getElementById(\'new_pw\').value = "";';
            echo 'document.getElementById(\'new_pw2\').value = "";';
            echo 'document.getElementById(\'div_changer_mdp\').style.display = "none";';
            echo 'alert(\''.$txt['pw_changed'].'\');';
            echo 'window.location.href = "index.php";';
        }
        
    break;
    
    case "identify_user":
        //Tuer les pr�c�dentes sessions
        $_SESSION = array();
        session_destroy();
        session_start();
        
        $_SESSION['user_language'] = $k['langage'];
        
        require_once ("main.functions.php");
            
        $sql="SELECT * FROM ".$k['prefix']."users WHERE ( login = '".$_POST['login']."' )";
        $req = mysql_query($sql) or die('Erreur SQL !<br>'.$sql.'<br>'.mysql_error());
        $data=mysql_fetch_array($req);
        
        
        if ( md5($_POST['pw']) == $data['pw'] ) {
            $_SESSION['autoriser'] = true;
            
            // Create a ramdom ID
            $key = "";
            $size = 50;
            $letters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            srand(time());
            for ($i=0;$i<$size;$i++)
            {
                $key.=substr($letters,(rand()%(strlen($letters))),1);
            }

            //Save account in SESSION
                $_SESSION['login'] = $_POST['login'];
                $_SESSION['user_id'] = $data['id'];
                $_SESSION['user_admin'] = $data['admin'];
                $_SESSION['user_gestionnaire'] = $data['gestionnaire'];
                $_SESSION['last_pw_change'] = $data['last_pw_change'];
                $_SESSION['last_pw'] = $data['last_pw'];
                $_SESSION['cle_session'] = $key;
                $_SESSION['fin_session'] = time() + $_POST['duree_session'] * 60;
                $_SESSION['derniere_connexion'] = $data['last_connexion'];
                $_SESSION['groupes_visibles'] = array();
                $_SESSION['groupes_interdits'] = array();
                if ( !empty($data['groupes_visibles'])) $_SESSION['groupes_visibles'] = implode(';',$data['groupes_visibles']);
                if ( !empty($data['groupes_interdits'])) $_SESSION['groupes_interdits'] = implode(';',$data['groupes_interdits']);
                $_SESSION['fonction_id'] = $data['fonction_id'];

            // Update table
            $sql = "UPDATE ".$k['prefix']."users SET key_tempo='".$_SESSION['cle_session']."', last_connexion='".mktime(date("h"),date("i"),date("s"),date("m"),date("d"),date("Y"))."' WHERE id=".$data['id'];
            $query = mysql_query($sql) or die('Erreur SQL !<br>'.$sql.'<br>'.mysql_error());          
            
            //r�cup�rer les droits de l'utilisateur
            IdentificationDesDroits($data['groupes_visibles'],$data['groupes_interdits'],$data['admin'],$data['fonction_id'],false);
            
            $_SESSION['hauteur_ecran'] = $_POST['hauteur_ecran'];
            
            echo 'document.location.href="index.php";';
        }else{
            echo 'document.getElementById(\'erreur_connexion\').style.display = "";';
            echo 'document.getElementById(\'ajax_loader_connexion\').style.display = "none";';
            echo 'document.getElementById(\'erreur_connexion\').innerHTML = "'.$txt['index_bas_pw'].'";';
        }
    break;
    
    case "augmenter_session":
        $_SESSION['fin_session'] = $_SESSION['fin_session']+3600;
        echo 'document.getElementById(\'temps_restant\').value = "'.$_SESSION['fin_session'].'";';
    break;
    
}


?>