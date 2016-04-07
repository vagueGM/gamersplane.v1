<?
	require_once(FILEROOT.'/javascript/markItUp/markitup.bbcode-parser.php');
	addPackage('forum');
	
	$threadID = intval($pathOptions[1]);
	$threadManager = new ThreadManager($threadID);

	$gameID = false;
	$isGM = false;
	$gms = array();
	if ($threadManager->isGameForum()) {
		$gameID = (int) $threadManager->getForumProperty('gameID');
		$game = $mongo->games->findOne(array('gameID' => $gameID), array('system' => true, 'players' => true));
		$system = $game['system'];
		$isGM = false;
		foreach ($game['players'] as $player) {
			if ($player['user']['userID'] == $currentUser->userID) 
				if ($player['isGM']) 
					$isGM = true;
			if ($player['isGM']) 
				$gms[] = $player['user']['userID'];
		}

		require_once(FILEROOT."/includes/packages/{$system}Character.package.php");
		$charClass = Systems::systemClassName($system).'Character';
	} else 
		$fixedGameMenu = false;

	$dispatchInfo['title'] = $threadManager->getThreadProperty('title').' | '.$dispatchInfo['title'];
	$dispatchInfo['description'] = $threadManager->getKeyPost()->message;
?>
<?	require_once(FILEROOT.'/header.php'); ?>
		<h1 class="headerbar" skew-element>{{thread.title}}</h1>
		<div hb-margined>
			<div id="threadMenu" class="clearfix">
				<div class="leftCol">
					<div id="breadcrumbs">
						<a ng-repeat="hForum in thread.forumHeritage" href="/forums/{{hForum.forumID}}/" ng-bind-html="hForum.title"></a>
					</div>
					<a href="/forums/{{thread.forumID}}/">Back to the forums</a>
				</div>
				<div class="rightCol alignRight">
					<p ng-if="loggedIn && thread.subscribed != 'f'" class="threadSub"><a id="forumSub" href="/forums/process/subscribe/{{threadID}}">{{thread.subscribed == 't'?'Unsubscribe from':'Subscribe to'}} thread</a></p>
					<form id="threadOptions" ng-if="thread.permissions.moderate" method="post" action="/forums/process/modThread/">
						<button type="submit" name="sticky" title="{{thread.sticky?'Uns':'S'}}ticky Thread" alt="{{thread.sticky?'Uns':'S'}}ticky Thread" ng-class="thread.sticky?'unsticky':'sticky'"></button>
						<button type="submit" name="lock" title="{{thread.sticky?'Unl':'L'}}ock Thread" alt="{{thread.sticky?'Unl':'L'}}ock Thread" ng-class="thread.sticky?'unlock':'lock'"></button>
					</form>
					<a ng-if="thread.permissions.write" href="/forums/post/<?=$threadID?>/" class="fancyButton" skew-element>Reply</a>
				</div>
			</div>
			<form id="poll" ng-if="!thread.locked && thread.poll" method="post" action="/forums/process/vote/">
				<p id="poll_question" ng-bind-html="thread.poll.question"></p>
				<p ng-if="!thread.poll.voted || thread.poll.allowRevoting">You may select {{thread.poll.optionsPerUser > 1?'up to ':''}}<strong>{{thread.poll.optionsPerUser}}</strong> option{{thread.poll.optionsPerUser > 1?'s':''}}</p>
				<ul>
					<li ng-repeat="option in thread.poll.options" class="clearfix">
						<span class="poll_input">
							<pretty-radio ng-if="thread.poll.optionsPerUser == 1" eleid="option_{{$index}}" radio="thread.poll.votes" r-value="$index"></pretty-radio>
							<pretty-checkbox ng-if="thread.poll.optionsPerUser > 1" eleid="option_{{$index}}" checkbox="thread.poll.votes" value="$index"></pretty-checkbox>
						</span>
						<label for="option_{{$index}}" class="pointer poll_option">{{option.option}}</label>
						<span ng-if="thread.poll.voted" class="poll_votesCast" ng-style="{ width: option.width }">{{option.numVotes}}, {{option.percentage}}%</span>
						</label>
					</li>
				</ul>
				<div id="poll_submit" ng-if="!thread.poll.voted || thread.poll.allowRevoting"><button type="submit" name="submit" class="fancyButton" skew-element>Vote</button></div>
			</form>
<?	
	$newPostMarked = false;
?>
			<div ng-repeat="post in thread.posts" class="postBlock clearfix" ng-class="{ 'postLeft': postSide == 'l', 'postRight': postSide == 'r', 'postAsChar': post.postAs, 'withCharAvatar': post.postAs && post.postAs.avatar }">
				<a name="p{{post.postID}}"></a>
				<a ng-if="post.newPost" name="newPost"></a>
				<a ng-if="post.lastPost" name="lastPost"></a>
				<div class="posterDetails">
					<avatar user="post.author" char="post.postAs"></avatar>
					<span ng-if="post.postAs">
						<p ng-if="post.postAs.permissions" class="charName"><a href="/characters/{{post.postAs.system}}/{{post.postAs.characterID}}/" ng-bind-html="post.postAs.name"></a></p>
						<p ng-if="!post.postAs.permissions" class="charName" ng-bind-html="post.postAs.name"></p>
					</span>
					<p class="posterName"><a href="/user/{{post.author.userID}}/" class="username" ng-bind-html="post.author.username"></a> <img ng-if="post.author.isGM" src="/images/gm_icon.png"><user-inactive last-activity="post.author.lastActivity"></user-inactive></p>
				</div>
				<div class="postContent">
					<div class="postPoint" ng-class="{ 'pointLeft': postSide == 'l', 'pointRight': postSide == 'r' }"></div>
					<header class="postHeader">
						<div class="postedOn">{{post.datePosted | amUtc | amLocal | amDateFormat: 'MMMM Do, YYYY h:mm a'}}</div>
						<div class="subject"><a href="?p={{post.postID}}#p{{post.postID}}" ng-bind-html="post.title"></a></div>
					</header>
					<div class="post">
						<span ng-bind-html="post.message"></span>
						<div ng-if="post.timesEdited != 0" class="editInfoDiv">Last edited {{post.lastEdit | amUtc | amLocal | amDateFormat: 'MMMM Do, YYYY h:mm a'}}, a total of {{post.timesEdited}} time{{post.timesEdited > 1?'s':''}}</div>
					</div>
					<div ng-if="post.rolls">
						<h4>Rolls</h4>
						<div class="rollInfo">
							<roll ng-repeat="roll in post.rolls" roll="roll" show-hidden="post.showRollHidden"></roll>
						</div>
					</div>
					<div ng-if="post.draws">
						<h4>Deck Draws</h4>
						<div ng-repeat="draw in post.draws">
							<div ng-bind-html="draw.reason"></div>
							<span ng-repeat="card in draw.cards" class="cardWrapper">
								<card ng-show="post.author.userID == currentUser.userID || card.visible" card-num="{{card.card}}" deck-type="{{draw.type}}" size="mid"></card>
								<div ng-if="post.author.userID == currentUser.userID" ng-attr-alt="{{card.visible?'Visible':Hidden}}" ng-attr-title="{{card.visible?'Visible':Hidden}}" ng-class="{ 'eyeIcon': true, 'hidden': !card.visible }" ng-click="toggleCardVis(post.postID, draw.deckID, card)"></div>
								<img ng-show="post.author.userID != currentUser.userID && !card.visible" src="/images/tools/cards/back.png" alt="Hidden Card" title="Hidden Card" class="cardBack mid">
							</span>
						</div>
					</div>
				</div>
				<div class="postActions">
<?
			if ($threadManager->getPermissions('write')) echo "						<a href=\"/forums/post/{$threadID}/?quote={$post->postID}\">Quote</a>\n";
			if (($post->author->userID == $currentUser->userID && !$threadManager->getThreadProperty('states[locked]')) || $threadManager->getPermissions('moderate')) {
				if ($threadManager->getPermissions('moderate') || $threadManager->getPermissions('editPost')) echo "					<a href=\"/forums/editPost/{$post->postID}/\">Edit</a>\n";
				if ($threadManager->getPermissions('moderate') || $threadManager->getPermissions('deletePost') && $post->postID != $threadManager->getThreadProperty('firstPostID') || $threadManager->getPermissions('deleteThread') && $post->postID == $threadManager->getThreadProperty('firstPostID')) echo "					<a href=\"/forums/delete/{$post->postID}/\" class=\"deletePost\">Delete</a>\n";
			}
?>
				</div>
			</div>
<?
			$postCount += 1;
			if ($forumOptions['postSide'] == 'c') $postSide = $postSide == 'Right'?'Left':'Right';

		$threadManager->displayPagination();
	
	if ($threadManager->getPermissions('moderate')) {
?>
			<div class="clearfix"><form id="quickMod" method="post" action="/forums/process/modThread/">
<?
	$sticky = $threadManager->thread->getStates('sticky')?'Unsticky':'Sticky';
	$lock = $threadManager->thread->getStates('locked')?'Unlock':'lock';
?>
				Quick Mod Actions: 
				<input type="hidden" name="threadID" value="<?=$threadID?>">
				<select name="action">
					<option value="lock"><?=ucwords($lock)?> Thread</option>
					<option value="sticky"><?=ucwords($sticky)?> Thread</option>
					<option value="move">Move Thread</option>
				</select>
				<button type="submit" name="go">Go</button>
			</form></div>
<?	} ?>
		</div>

<?
	if (($threadManager->getPermissions('write') && $currentUser->userID != 0 && !$threadManager->getThreadProperty('states[locked]')) || $threadManager->getPermissions('moderate')) {
		$characters = array();
		if ($gameID) {
			$rCharacters = $mongo->characters->find(array('game.gameID' => $gameID, 'game.approved' => true, 'user.userID' => $currentUser->userID), array('characterID' => true, 'name' => true));
			$characters = array();
			foreach ($rCharacters as $character)
				if (strlen($character['name'])) 
					$characters[$character['characterID']] = $character['name'];
		}
?>
		<form id="quickReply" method="post" action="/forums/process/post/">
			<h2 class="headerbar hbDark">Quick Reply</h2>
			<input type="hidden" name="threadID" value="<?=$threadID?>">
			<input type="hidden" name="title" value="Re: <?=htmlspecialchars($threadManager->getThreadProperty('title'))?>">
			<div class="hbdMargined">
<?		if (sizeof($characters)) { ?>
				<div id="charSelect" class="tr">
					<label>Post As:</label>
					<div><select name="postAs">
						<option value="p"<?=$currentChar == null?' selected="selected"':''?>>Player</option>
<?			foreach ($characters as $characterID => $name) { ?>
						<option value="<?=$characterID?>"<?=$currentChar == $characterID?' selected="selected"':''?>><?=$name?></option>
<?			} ?>
					</select></div>
				</div>
<?		} ?>			
				<textarea id="messageTextArea" name="message"></textarea>
			</div>
			<div id="submitDiv" class="alignCenter">
				<button type="submit" name="post" class="fancyButton">Post</button>
				<button type="submit" name="advanced" class="fancyButton">Advanced</button>
			</div>
		</form>
<?
	} elseif ($threadManager->getThreadProperty('states[locked]')) 
		echo "\t\t\t<h2 class=\"alignCenter\">Thread locked</h2>\n";
	else 
		echo "\t\t\t<h2 class=\"alignCenter\">You do not have permission to post in this thread.</h2>\n";
	
	require_once(FILEROOT.'/footer.php');
?>