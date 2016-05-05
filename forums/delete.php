<?	require_once(FILEROOT.'/header.php'); ?>
		<h1 class="headerbar">Delete {{deleteType}}</h1>
		<div hb-margined>
			<p class="alignCenter">Are you sure you wanna delete this {{deleteType}}?</p>
			<p class="alignCenter">This cannot be reversed!</p>
			<div class="postPreview">
				<p><strong ng-bind-html="post.title | trustHTML"></strong></p>
				<p ng-bind-html="post.message | trustHTML"></p>
			</div>
			
			<form ng-submit="deletePost()" class="alignCenter">
				<button type="submit" ng-click="buttonPressed = 'delete'" class="fancyButton">Delete</button>
				<button type="submit" ng-click="buttonPressed = 'cancel'" class="fancyButton">Cancel</button>
			</form>
		</div>
<?	require_once(FILEROOT.'/footer.php'); ?>