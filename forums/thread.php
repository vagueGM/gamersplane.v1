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
					<p ng-if="loggedIn && thread.subscribed != 'f'" class="threadSub"><a id="forumSub" ng-click="toggleSubscribe()">{{thread.subscribed == 't'?'Unsubscribe from':'Subscribe to'}} thread</a></p>
					<div id="threadOptions" ng-if="thread.permissions.moderate" method="post">
						<button type="submit" name="sticky" title="{{thread.sticky?'Uns':'S'}}ticky Thread" alt="{{thread.sticky?'Uns':'S'}}ticky Thread" ng-click="toggleThreadState('sticky')" ng-class="thread.sticky?'unsticky':'sticky'"></button>
						<button type="submit" name="lock" title="{{thread.locked?'Unl':'L'}}ock Thread" alt="{{thread.locked?'Unl':'L'}}ock Thread" ng-click="toggleThreadState('locked')" ng-class="thread.locked?'unlock':'lock'"></button>
					</div>
					<a ng-if="thread.permissions.write" href="/forums/post/<?=$threadID?>/" class="fancyButton" skew-element>Reply</a>
				</div>
			</div>
			<form id="poll" ng-if="thread.poll" method="post" ng-submit="pollVote($event)">
				<p id="poll_question" ng-bind-html="thread.poll.question"></p>
				<p ng-if="thread.poll.canVote">You may select {{thread.poll.optionsPerUser > 1?'up to ':''}}<strong>{{thread.poll.optionsPerUser}}</strong> option{{thread.poll.optionsPerUser > 1?'s':''}}</p>
				<ul>
					<li ng-repeat="option in thread.poll.options" class="clearfix">
						<span ng-if="thread.poll.canVote" class="poll_input">
							<pretty-radio ng-if="thread.poll.optionsPerUser == 1" eleid="option_{{$index}}" radio="thread.poll.votes" r-value="$index"></pretty-radio>
							<pretty-checkbox ng-if="thread.poll.optionsPerUser > 1" eleid="option_{{$index}}" checkbox="thread.poll.votes" value="$index"></pretty-checkbox>
						</span>
						<label for="option_{{$index}}" class="pointer poll_option">{{option.option}}</label>
						<span ng-if="!loggedIn || thread.poll.voted" class="poll_votesCast" ng-style="{ width: option.width }">{{option.numVotes}}, {{option.percentage}}%</span>
					</li>
				</ul>
				<div id="poll_submit" ng-if="thread.poll.canVote"><button type="submit" name="submit" class="fancyButton" skew-element>Vote</button></div>
			</form>
			<div ng-repeat="post in thread.posts" class="postBlock clearfix" ng-class="{ 'postLeft': post.postSide == 'l', 'postRight': post.postSide == 'r', 'postAsChar': post.postAs, 'withCharAvatar': post.postAs && post.postAs.avatar }">
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
					<div class="postPoint" ng-class="{ 'pointLeft': post.postSide == 'l', 'pointRight': post.postSide == 'r' }"></div>
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
					<a ng-if="thread.permissions.write" href="/forums/post/{{thread.threadID}}/?quote={{post.postID}}">Quote</a>
					<a ng-if="post.permissions.edit" href="/forums/editPost/{{post.postID}}/">Edit</a>
					<a ng-if="post.permissions.delete" href="/forums/delete/{{post.postID}}/" class="deletePost" colorbox>Delete</a>
				</div>
			</div>
			<div class="clearfix"><paginate num-items="pagination.numPosts" items-per-page="pagination.itemsPerPage" current="pagination.current" change-func="changePage"></paginate></div>
			<div ng-if="thread.permissions.moderate" class="clearfix"><form class="quickMod" ng-submit="submitQuickMod()">
				Quick Mod Actions: 
				<combobox id="quickMod" data="quickMod.combobox" value="quickMod.action" select returnAs="value"></combobox>
				<button type="sfubmit" name="go">Go</button>
			</form></div>
		</div>

		<form ng-if="loggedIn && ((thread.permissions.write && !thread.locked) || thread.permissions.moderate)" id="quickReply" method="post" action="/forums/post/<?=$threadID?>/">
			<h2 class="headerbar hbDark" skew-element>Quick Reply</h2>
			<div hb-margined>
				<div ng-if="thread.characters != null" id="charSelect" class="tr">
					<label>Post As:</label>
					<div><combobox data="characters" value="quickPost.postAs" select returnAs="value"></combobox></div>
				</div>
				<textarea ng-model="quickPost.message" mark-it-up></textarea>
			</div>
			<div id="submitDiv" class="alignCenter">
				<button type="submit" name="post" ng-click="saveQuickPost($event)" class="fancyButton" skew-element>Post</button>
				<button type="submit" name="advanced" class="fancyButton" ng-click="advancedPost" skew-element>Advanced</button>
			</div>
		</form>
		<h2 ng-if="loggedIn && thread.locked && !thread.permissions.moderate" class="alignCenter">Thread locked</h2>
		<h2 ng-if="loggedIn && !thread.permissions.write && !thread.permissions.moderate" class="alignCenter">You do not have permission to post in this thread.</h2>
		<h2 ng-if="!loggedIn" class="alignCenter"><a href="/login/" colorbox>Login</a> or <a href="/register/">sign up to join this conversation!</a>
<?	require_once(FILEROOT.'/footer.php'); ?>