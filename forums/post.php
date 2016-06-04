<?
	require_once(FILEROOT.'/javascript/markItUp/markitup.bbcode-parser.php');
	addPackage('forum');

	$noChat = false;
	$firstPost = false;
	$editPost = $pathOptions[0] == 'editPost'?true:false;

	if ($editPost) {
		$postID = intval($pathOptions[1]);
		$post = new Post($postID);
		$threadManager = new ThreadManager($post->getThreadID());
		if ($postID == $threadManager->getThreadProperty('firstPostID'))
			$firstPost = true;

		if ($post->getAuthor('userID') != $currentUser->userID && !$threadManager->getPermissions('moderate'))
			$noChat = true;
		elseif ($threadManager->getThreadProperty('states[locked]') && !$threadManager->getPermissions('moderate'))
			$noChat = true;
		elseif (!$threadManager->getPermissions('write'))
			$noChat = true;
	} elseif ($pathOptions[0] == 'newThread') {
		$firstPost = true;

		$forumID = intval($pathOptions[1]);
		$threadManager = new ThreadManager(null, $forumID);
		$threadManager->thread->forumID = $forumID;
		$post = new Post();
		if (!$threadManager->getPermissions('createThread')) $noChat = true;
	} elseif ($pathOptions[0] == 'post') {
		$threadID = intval($pathOptions[1]);
		try {
			$threadManager = new ThreadManager($threadID);
			$post = new Post();

			if ($threadManager->getThreadProperty('states[locked]') || !$threadManager->getPermissions('write'))
				$noChat = true;
			else {
				if (isset($_SESSION['message'])) {
					$post->message = $_SESSION['message'];
					unset($_SESSION['message']);
				} elseif (isset($_GET['quote'])) {
					$quoteID = intval($_GET['quote']);
					if ($quoteID) {
						$quoteInfo = $mysql->query("SELECT u.username, p.message FROM users u, posts p WHERE p.postID = {$quoteID} AND p.authorID = u.userID");
						$quoteInfo = $quoteInfo->fetch();
						$gameID = $threadManager->forumManager->forums[$threadManager->getThreadProperty('forumID')]->gameID;
						if ($gameID) {
							$game = $mongo->games->findOne(array('gameID' => (int) $gameID, 'players' => array('$elemMatch' => array('user.userID' => $currentUser->userID, 'isGM' => true))), array('players.$'));
							$isGM = $game['players'][0]['isGM'];
							if (!$isGM)
								$quoteInfo['message'] = Post::cleanNotes($quoteInfo['message']);
						}
						$post->message = '[quote="'.$quoteInfo['username'].'"]'.$quoteInfo['message'].'[/quote]';
					}
				}
			}
		} catch (Exception $e) { $noChat = true; }
	} else
		$noChat = true;

	if ($noChat) { header('Location: /forums/'); exit; }

	$fillVars = $formErrors->getErrors('post');

	if ($_GET['preview'])
		$fillVars = $_SESSION['previewVars'];
	else
		unset($_SESSION['previewVars']);

	$gameID = false;
	$isGM = false;
	if ($threadManager->getForumProperty('gameID')) {
		$gameID = (int) $threadManager->getForumProperty('gameID');
		$returnFields = array('system' => true, 'players' => true);
		if ($threadManager->getPermissions('addDraws'))
			$returnFields['decks'] = true;
		$game = $mongo->games->findOne(array('gameID' => $gameID), $returnFields);
		$system = $game['system'];
		$isGM = false;
		foreach ($game['players'] as $player) {
			if ($player['user']['userID'] == $currentUser->userID) {
				if ($player['isGM'])
					$isGM = true;
				break;
			}
		}

		require_once(FILEROOT."/includes/packages/{$system}Character.package.php");
		$charClass = Systems::systemClassName($system).'Character';
		$rCharacters = $mongo->characters->find(array('game.gameID' => $gameID, 'user.userID' => $currentUser->userID), array('characterID' => true, 'name' => true));
		$characters = array();
		foreach ($rCharacters as $character)
			if (strlen($character['name']))
				$characters[$character['characterID']] = $character['name'];
	} else
		$fixedGameMenu = false;

	$rollsAllowed = $threadManager->getThreadProperty('allowRolls')?true:false;
	$drawsAllowed = false;
	if ($gameID && $threadManager->getPermissions('addDraws')) {
		$decks = $game['decks'];
		if (!$isGM) {
			foreach ($decks as $key => $deck)
				if (!in_array($currentUser->userID, $deck['permissions']))
					unset($decks[$key]);
			$decks = array_values($decks);
		}
		if (sizeof($decks))
			$drawsAllowed = true;
	}

	require_once(FILEROOT.'/header.php');
?>
<?	if ($_GET['errors'] && $formErrors->errorsExist()) { ?>
		<div class="alertBox_error"><ul>
<?
		if ($formErrors->checkError('overdrawn')) echo "			<li>Incorrect number of cards drawn.</li>\n";
		if ($formErrors->checkError('noTitle')) echo "			<li>You can't leave the title blank.</li>\n";
		if ($formErrors->checkError('noMessage')) echo "			<li>You can't leave the message blank.</li>\n";
		if ($formErrors->checkError('noDrawReason')) echo "			<li>You left draw reasons blank.</li>\n";
		if ($formErrors->checkError('noPoll')) echo "			<li>You did not provide a poll question.</li>\n";
		if ($formErrors->checkError('noOptions')) echo "			<li>You did not provide poll options or provided too few (minimum 2).</li>\n";
		if ($formErrors->checkError('noOptionsPerUser')) echo "			<li>You did not provide a valid number for \"Options per user\".</li>\n";
		if ($formErrors->checkError('badRoll')) echo "			<li>One or more of your roll entries are malformed. Please make sure they are in the right format.</li>\n";
?>
		</ul></div>
<?
	}
?>
		<div class="clearfix" hb-margined>
			<breadcrumbs forums="thread.forumHeritage"></breadcrumbs>
			<a ng-if="pageType != 'newThread'" id="returnToThread" href="/forums/thread/{{thread.threadID}}/">Return to thread</a>
		</div>
		<h1 class="headerbar" skew-element>{{header}}</h1>

<?	if ($_GET['preview'] && strlen($fillVars['message']) > 0) { ?>
		<h2>Preview:</h2>
		<div id="preview">
			<?=BBCode2Html(printReady($fillVars['message']))."\n"?>
		</div>
		<hr>

<? } ?>
		<form ng-submit="save()">
<?
	if ($fillVars)
		$title = printReady($fillVars['title']);
	elseif (!strlen($post->getTitle()) && $threadManager->getThreadID())
		$title = 'Re: '.$threadManager->getThreadProperty('title');
	else
		$title = printReady($post->title, array('stripslashes'));
?>
			<div id="basicPostInfo" class="hbMargined">
				<div class="table">
					<div>
						<label for="title">Title:</label>
						<div><input id="title" type="text" ng-model="post.title" maxlength="50"></div>
					</div>
					<div ng-if="thread.game" class="tr">
						<label>Post As:</label>
						<div><combobox data="combobox.characters" value="post.postAs" returnAs="value" select></combobox></div>
					</div>
				</div>
				<textarea id="messageTextArea" ng-model="post.message" mark-it-up></textarea>
			</div>

			<div ng-if="firstPost || thread.options.allowRolls || thread.options.allowDraws">
				<div id="optionControls" class="clearfix"><div class="trapezoid sectionControls" trapezoidify="down">
					<div>
						<a ng-if="firstPost" href="" ng-click="setOptionsState('options')" class="section_options" ng-class="{ current: options.state == 'options' }">{{optionsStates['options']}}</a>
						<a ng-if="firstPost" href="" ng-click="setOptionsState('poll')" class="section_poll" ng-class="{ current: options.state == 'poll' }">{{optionsStates['poll']}}</a>
						<a ng-if="(firstPost && (thread.permissions.addRolls || thread.permissions.addDraws)) || thread.options.allowRolls || (thread.options.allowDraws && decks.length)" href="" ng-click="setOptionsState('rolls_decks')" class="section_rolls_decks" ng-class="{ current: options.state == 'rolls_decks' }">{{optionsStates['rolls_decks']}}</a>
					</div>
				</div></div>
				<h2 class="headerbar hbDark" skew-element>{{options.title}}</h2>
				<div ng-if="options.state == 'options'" id="threadOptions" class="section_options" hb-margined="dark">
					<p ng-if="thread.permissions.moderate"><pretty-checkbox checkbox="post.options.sticky"></pretty-checkbox> Sticky thread</p>
					<p ng-if="thread.permissions.moderate"><pretty-checkbox checkbox="post.options.locked"></pretty-checkbox> Lock thread</p>
					<p ng-if="thread.permissions.addRolls"><pretty-checkbox checkbox="thread.options.allowRolls"></pretty-checkbox> Allow adding rolls to posts (if this box is unchecked, any rolls added to this thread will be ignored)</p>
					<p ng-if="thread.permissions.addDraws"><pretty-checkbox checkbox="thread.options.allowDraws"></pretty-checkbox> Allow adding deck draws to posts (if this box is unchecked, any draws added to this thread will be ignored)</p>
				</div>
				<div ng-if="options.state == 'poll'" id="poll" class="section_poll" hb-margined="dark">
					<div ng-if="state == 'edit'" class="clearfix">
						<label for="allowRevoting"><b>Delete Poll:</b></label>
						<div><pretty-checkbox eleid="deletePoll" checkbox="thread.poll.delete"></pretty-checkbox> If checked, your poll will be deleted and cannot be recovered.</div>
					</div>
					<div class="tr clearfix">
						<label for="pollQuestion" class="textLabel"><b>Poll Question:</b></label>
						<div><input id="pollQuestion" type="text" ng-model="thread.poll.question" class="borderBox"></div>
					</div>
					<div class="tr clearfix">
						<label for="pollOption" class="textLabel">
							<b>Poll Options:</b>
							<p>Place each option on a new line. You may enter up to <b>25</b> options.</p>
						</label>
						<div><textarea id="pollOptions" ng-model="thread.poll.options"></textarea></div>
					</div>
					<div class="tr clearfix">
						<label for="optionsPerUser" class="textLabel"><b>Options per user:</b></label>
						<div><input id="optionsPerUser" type="text" ng-model="thread.poll.optionsPerUser" class="borderBox"></div>
					</div>
					<div class="tr clearfix">
						<label for="allowRevoting"><b>Allow Revoting:</b></label>
						<div><pretty-checkbox eleid="allowRevoting" checkbox="thread.poll.allowRevoting"></pretty-checkbox> If checked, people will be allowed to change their votes.</div>
					</div>
				</div>
				<div ng-if="options.state == 'rolls_decks'" id="rolls_decks" class="section_rolls_decks" hb-margined="dark">
					<div ng-if="thread.options.allowRolls" id="rolls">
						<h3 ng-if="thread.options.allowDraws" id="rollsHeader">Rolls</h3>
						<div id="rollExplination">
							For "Basic" type rolls, Enter the text roll in the following format:<br>
							(number of dice)d(dice type)+/-(modifier), i.e. 2d6+4, 1d10-2<br>
							The roll will automatically be added to your post when you submit it.
						</div>
						<div ng-if="post.rolls.prev" id="postedRolls">
							<h3>Posted Rolls</h3>
<?
				$visText = array(1 => '[Hidden Roll/Result]', '[Hidden Dice &amp; Roll]', '[Everything Hidden]');
				$hidden = false;
				$showAll = false;
				$first = true;
				foreach ($post->rolls as $roll) {
					$showAll = $isGM || $currentUser->userID == $post->author->userID?true:false;
					$hidden = false;
?>
							<div class="rollInfo">
								<select name="nVisibility[<?=$roll->getRollID()?>]">
									<option value="0"<?=$roll->getVisibility() == 0?' selected="selected"':''?>>Hide Nothing</option>
									<option value="1"<?=$roll->getVisibility() == 1?' selected="selected"':''?>>Hide Roll/Result</option>
									<option value="2"<?=$roll->getVisibility() == 2?' selected="selected"':''?>>Hide Dice &amp; Roll</option>
									<option value="3"<?=$roll->getVisibility() == 3?' selected="selected"':''?>>Hide Everything</option>
								</select>
								<div>
<?
					$roll->showHTML($showAll);
?>
								</div>
								<input type="hidden" name="oVisibility[<?=$roll->getRollID()?>]" value="<?=$roll->getVisibility()?>">
							</div>
<?				} ?>
						</div>
						<div id="addRoll" ng-click="addRoll">
							<span>Add new roll: </span>
							<combobox data="combobox.diceTypes" value="combobox.values.addDice" returnAs="value" select></combobox>
							<button ng-click="addRoll()" class="fancyButton" skew-element>Add</button>
						</div>
						<div id="newRolls">
							<new-roll ng-repeat="(key, roll) in post.rolls" data="roll"></new-roll>
						</div>
					</div>
					<div ng-if="thread.options.allowDraws && decks.length" id="draws">
						<h3 ng-if="thread.options.allowRolls" id="decksHeader">Decks</h3>
						<p>Please remember, any cards you draw will be only visible to you until you reveal them. Reveal them by clicking them. An eye icon indicates they're visible, while an eye with a red slash through them indiates a hidden card.</p>
						<div id="decksTable">
							<div ng-repeat="deck in decks" ng-class="{ 'titleBuffer': $first }">
								<p><b ng-bind-html="deck.label | trustHTML"></b> has {{deck.size - deck.position + 1}} cards left.</p>
								<div ng-if="post.draws[deck.deckID]">
									<p>Cards Drawn: {{post.draws[deck.deckID].reason}}</p>
									<card ng-repeat="card in post.draws[deck.deckID].cards" card-num="{{card.card}}" deck-type="{{deck.type}}" size="mini"></card>
								</div>
								<div ng-if="!post.draws[deck.deckID]">
									<span class="reason"><input type="text" ng-model="post.draws[deck.deckID].reason" maxlength="100"></span>
									<span class="draw">Draw <input type="text" ng-model="post.draws[deck.deckID].draw" maxlength="2"> cards</span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div id="submitDiv" class="alignCenter">
				<button type="submit" class="fancyButton" skew-element>{{pageType != 'Edit'?'Post':'Edit'}}</button>
				<button type="submit" ng-click="preview()" class="fancyButton" skew-element>Preview</button>
            </div>
		</form>
<? require_once(FILEROOT.'/footer.php'); ?>
