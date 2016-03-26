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
<?	if (!$threadManager->getThreadProperty('states[locked]') && $threadManager->getPoll()) { ?>
			<form id="poll" ng-if="!thread.locked && thread.poll" method="post" action="/forums/process/vote/">
				<input type="hidden" name="threadID" value="{{threadID}}">
				<p id="poll_question" ng-bind-html="thread.poll.question"></p>
<? 
		$castVotes = $threadManager->getVotesCast();
		$allowVote = sizeof($castVotes) && $threadManager->getPollProperty('allowRevoting') || sizeof($castVotes) == 0;
		if ($allowVote) 
			echo "				<p>You may select ".($threadManager->getPollProperty('optionsPerUser') > 1?'up to ':'')."<b>".$threadManager->getPollProperty('optionsPerUser')."</b> option".($threadManager->getPollProperty('optionsPerUser') > 1?'s':'').".</p>\n";

		$totalVotes = $threadManager->getVoteTotal();
		$highestVotes = $threadManager->getVoteMax();
?>
				<ul>
<?
		foreach ($threadManager->getPollProperty('options') as $pollOptionID => $option) {
			echo "					<li class=\"clearfix\">\n";
			if ($allowVote) {
				if ($threadManager->getPollProperty('optionsPerUser') == 1) 
					echo "						<div class=\"poll_input\"><input type=\"radio\" name=\"votes\" value=\"{$pollOptionID}\"".($option->voted?' checked="checked"':'')."></div>\n";
				else 
					echo "						<div class=\"poll_input\"><input type=\"checkbox\" name=\"votes\" value=\"{$pollOptionID}\"".($option->voted?' checked="checked"':'')."></div>\n";
			}
			echo "						<div class=\"poll_option\">".printReady($option->option)."</div>\n";
			if (sizeof($castVotes)) {
				echo "						<div class=\"poll_votesCast\" ".($option->votes?' style="width: '.(100 + floor($option->votes / $highestVotes * 425)).'px"':'').">".$option->votes.", ".floor($option->votes / $totalVotes * 100)."%</div>\n";
			}
			echo "					</li>\n";
		}
?>
				</ul>
<?		if ($allowVote) { ?>
				<div id="poll_submit"><button type="submit" name="submit" class="fancyButton">Vote</button></div>
<?		} ?>
			</form>
<?
	}
	
	$newPostMarked = false;
?>
			<div ng-repeat="post in thread.posts" class="postBlock clearfix" ng-class="{ 'postLeft': postSide == 'l', 'postRight': postSide == 'r', 'postAsChar': post.postAs, 'withCharAvatar': post.postAs && post.postAs.avatar }">
				<a name="p{{post.postID}}"></a>
				<a ng-if="post.newPost" name="newPost"></a>
				<a ng-if="post.lastPost" name="lastPost"></a>
				<div class="posterDetails">
					<avatar user="post.author" char="post.postAs"></avatar>
<?
			if ($postAsChar) {
				$character->getForumTop($post->author, in_array($post->author->userID, $gms));
			} else {
?>
					<p class="posterName"><a href="/user/<?=$post->author->userID?>/" class="username"><?=$post->author->username?></a><?=in_array($post->author->userID, $gms)?' <img src="/images/gm_icon.png">':''?><?=User::inactive($post->author->lastActivity)?></p>
<?			} ?>
				</div>
				<div class="postContent">
					<div class="postPoint point<?=$postSide == 'Right'?'Left':'Right'?>"></div>
					<header class="postHeader">
						<div class="postedOn convertTZ"><?=date('M j, Y g:i a', strtotime($post->datePosted))?></div>
						<div class="subject"><a href="?p=<?=$post->postID?>#p<?=$post->postID?>"><?=strlen($post->title)?printReady($post->title):'&nbsp'?></a></div>
					</header>
<?
			echo "\t\t\t\t\t<div class=\"post\">\n";
			echo printReady(BBCode2Html($post->message))."\n";
			if ($post->timesEdited) { echo "\t\t\t\t\t\t".'<div class="editInfoDiv">Last edited <span  class="convertTZ">'.date('F j, Y g:i a', strtotime($post->lastEdit)).'</span>, a total of '.$post->timesEdited.' time'.(($post->timesEdited > 1)?'s':'')."</div>\n"; }
			echo "\t\t\t\t\t</div>\n";
			
			if (sizeof($post->rolls)) {
?>
					<div class="rolls">
						<h4>Rolls</h4>
<?
				foreach ($post->rolls as $roll) {
					$showAll = $isGM || $currentUser->userID == $post->author->userID?true:false;
?>
						<div class="rollInfo">
<?					$roll->showHTML($showAll); ?>
						</div>
<?				} ?>
					</div>
<?
	 		}
			
			if (sizeof($post->draws)) {
				$visText = array(1 => '[Hidden Roll/Result]', '[Hidden Dice &amp; Roll]', '[Everything Hidden]');
				$hidden = false;
?>
					<h4>Deck Draws</h4>
<?				foreach ($post->draws as $draw) { ?>
					<div><?=printReady($draw['reason'])?></div>
<?					if ($post->author->userID == $currentUser->userID) { ?>
						<form method="post" action="/forums/process/cardVis/">
							<input type="hidden" name="drawID" value="<?=$draw['_id']?>">
<?						foreach ($draw['draw'] as $key => $cardDrawn) { ?>
								<button type="submit" name="position" value="<?=$key?>">
									<?=getCardImg($cardDrawn['card'], $draw['type'], 'mid')?>
<?							$visText = $cardDrawn['visible']?'Visible':'Hidden'; ?>
									<div alt="<?=$visText?>" title="<?=$visText?>" class="eyeIcon<?=$visText == 'Hidden'?' hidden':''?>"></div>
								</button>
<?						} ?>
						</form>
<?					} else { ?>
						<div>
<?
						foreach ($draw['draw'] as $key => $cardDrawn) {
							if ($cardDrawn['visible']) {
?>
							<?=getCardImg($cardDrawn['card'], $draw['type'], 'mid')?>
<?							} else { ?>
							<img src="/images/tools/cards/back.png" alt="Hidden Card" title="Hidden Card" class="cardBack mid">
<?
							}
						}
?>
						</div>
<?
					}
				}
	 		}
?>
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