<?php 
/* 
 *	Made by Samerton
 *  http://worldscapemc.co.uk
 *
 *  License: MIT
 */

// Set the page name for the active link in navbar
$page = "forum";

// User must be logged in to proceed
if(!$user->isLoggedIn()){
	Redirect::to('/forum');
	die();
}

// Initialise
$forum = new Forum();

require('inc/includes/html/library/HTMLPurifier.auto.php'); // HTML Purifier


if(isset($_GET["pid"]) && isset($_GET["tid"])){
	if(is_numeric($_GET["pid"]) && is_numeric($_GET["tid"])){
		$post_id = $_GET["pid"];
		$topic_id = $_GET["tid"];
	} else {
		Redirect::to('/forum/error/?error=not_exist');
		die();
	}
} else {
	Redirect::to('/forum/error/?error=not_exist');
	die();
}


$post_editing = $queries->getWhere("posts", array("id", "=", $post_id));


if($user->data()->id === $post_editing[0]->post_creator || $user->data()->group_id == 2 || $user->data()->group_id == 3){
	if(Input::exists()) {
		if(Token::check(Input::get('token'))) {
			$validate = new Validate();
			$validation = $validate->check($_POST, array(
				'content' => array(
					'required' => true,
					'min' => 2,
					'max' => 20480
				)
			));
			if($validation->passed()){
				try {
					$queries->update("posts", $post_id, array(
						'post_content' => htmlspecialchars(Input::get('content'))
					));
					Session::flash('success_post', '<div class="alert alert-info alert-dismissable"> <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>Post edited.</div>');
					Redirect::to('/forum/view_topic/?tid=' . $topic_id . '&amp;pid=' . $post_id);
					die();
				} catch(Exception $e){
					die($e->getMessage());
				}
			} else {
				$error_string = "";
				foreach($validation->errors() as $error) {
					$error_string .= ucfirst($error) . '<br />';
				}
				Session::flash('failure_post', '<div class="alert alert-danger alert-dismissable"> <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>' . $error_string . '</div>');
			}
		} else {
			// Bad token - TODO: improve this
		}
	}
} else {
	Redirect::to("/forum");
	die();
}

$token = Token::generate();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo $sitename; ?> Forum - Editing post">
    <meta name="author" content="Samerton">
    <meta name="robots" content="noindex">
    <link rel="icon" href="/favicon.ico">

    <title><?php echo $sitename; ?> &bull; Forum - Edit Post</title>
	
	<?php require('inc/templates/header.php'); ?>
	
	<!-- Custom style -->
	<style>
	textarea {
		resize: none;
	}
	</style>
	
  </head>

  <body>

	<?php require('inc/templates/navbar.php'); ?>

    <div class="container">
  
	  <h2>Editing post</h2>
	  <?php 
		if(Session::exists('failure_post')){
			echo Session::flash('failure_post');
		}
	  ?>
		<form action="" method="post">
			<textarea name="content" id="editor" rows="3">
			<?php
		    // Initialise HTML Purifier
			$config = HTMLPurifier_Config::createDefault();
			$config->set('HTML.Doctype', 'XHTML 1.0 Transitional');
			$config->set('URI.DisableExternalResources', false);
			$config->set('URI.DisableResources', false);
			$config->set('HTML.Allowed', 'u,p,b,i,small,blockquote,span[style],span[class],p,strong,em,li,ul,ol,div[align],br,img');
			$config->set('CSS.AllowedProperties', array('text-align', 'float', 'color','background-color', 'background', 'font-size', 'font-family', 'text-decoration', 'font-weight', 'font-style', 'font-size'));
			$config->set('HTML.AllowedAttributes', 'href, src, height, width, alt, class, *.style');
			$purifier = new HTMLPurifier($config);

			$clean = $purifier->purify(htmlspecialchars_decode($post_editing[0]->post_content));
			?>
			</textarea>
			<br />
			<?php echo '<input type="hidden" name="token" value="' .  $token . '">'; ?>
			<button type="submit" class="btn btn-danger">
			  Submit
			</button>
		</form>	  
      <hr>
	  
	  <?php require('inc/templates/footer.php'); ?> 
	  
    </div>
		
	<?php require('inc/templates/scripts.php'); ?>
	<script src="/assets/js/ckeditor.js"></script>
	<script type="text/javascript">
		CKEDITOR.replace( 'editor', {
			// Define the toolbar groups as it is a more accessible solution.
			toolbarGroups: [
				{"name":"basicstyles","groups":["basicstyles"]},
				{"name":"links","groups":["links"]},
				{"name":"paragraph","groups":["list","align"]},
				{"name":"insert","groups":["insert"]},
				{"name":"styles","groups":["styles"]},
				{"name":"about","groups":["about"]}
			],
			// Remove the redundant buttons from toolbar groups defined above.
			removeButtons: 'Anchor,Styles,Specialchar,Font,About,Flash,Iframe'
		} );
		CKEDITOR.config.extraAllowedContent = 'blockquote small';
	    <?php 
	    // Insert
	    if(!Session::exists('failure_post')){
	    ?>
		CKEDITOR.on('instanceReady', function(ev) {
		     CKEDITOR.instances.editor.insertHtml('<?php echo str_replace(array("\r", "\n"), '', $clean); ?>');
		});
		<?php
		}
		?>
	</script>

  </body>
</html>