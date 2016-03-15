<?
	addPackage('forum');

	$forumID = intval($pathOptions[0]);
	$forumManager = new ForumManager($forumID);

	if (!$forumManager->displayCheck()) { header('Location: /forums'); exit; }

	if ($forumManager->getForumProperty($forumID, 'gameID')) {
		$gameID = $forumManager->getForumProperty($forumID, 'gameID');
		$fixedGameMenu = true;
	} else
		$gameID = false;

	if ($forumID) 
		$dispatchInfo['title'] = $forumManager->getForumProperty($forumID, 'title').' | '.$dispatchInfo['title'];
?>
<?	require_once(FILEROOT.'/header.php'); ?>
		<h1 class="headerbar" skew-element>Forum<span ng-bind-html="forumID?' - ' + currentForum.title:'s'"></span></h1>

		<div id="topLinks" class="clearfix hbMargined">
			<div id="breadcrumbs" ng-if="forumID != 0">
				<a href="" ng-repeat="hForumID in currentForum.heritage" ng-bind-html="forums[hForumID].title"></a>
			</div>
			<div class="floatRight alignRight">
				<div ng-if="forumID == 0"><a href="/forums/search/?search=latestPosts">Latest Posts</a></div>
				<div><a ng-if="currentForum.permissions.admin" href="/forums/acp/{{forumID}}/">Administrative Control Panel</a></div>
			</div>
			<div class="floatLeft alignLeft">
				<div>Be sure to read and follow the <a href="/forums/rules">guidelines for our forums</a>.</div>
			</div>
		</div>
		<div ng-repeat="fGroup in mainStructure" class="tableDiv">
			<div class="clearfix">
				<div ng-if="loggedIn && fGroup.forumID == 2" class="pubGameToggle hbdMargined">
					<span>Show public games: </span>
					<a href="/forums/process/togglePubGames/" class="ofToggle disable" ng-class="{ 'on': currentUser.usermeta.showPubGames }"></a>
				</div>
				<h2 class="trapezoid redTrapezoid">{{forums[fGroup.forumID].type == 'c'?forums[fGroup.forumID].title:'Subforums'}}</h2>
			</div>
			<div class="tr headerTR headerbar hbDark" skew-element>
				<div class="td icon">&nbsp;</div>
				<div class="td name">Forum</div>
				<div class="td numThreads"># of Threads</div>
				<div class="td num1Posts"># of Posts</div>
				<div class="td lastPost">Last Post</div>
			</div>
			<div class="sudoTable forumList hbdMargined" hb-margined>
				<div ng-repeat="cForumID in fGroup.forums" class="tr" ng-class="{ 'noPosts': !forums[cForumID].newPosts }">
					<div class="td icon"><div class="forumIcon" ng-class="{ 'newPosts': forums[cForumID].newPosts }" title="{{forums[cForumID].newPosts?'New':'No new'}} posts in forum" alt="{{forums[cForumID].newPosts?'New':'No new'}} posts in forum"></div></div>
					<div class="td name">
						<a href="/forums/{{cForumID}}/" ng-bind-html="forums[cForumID].title"></a>
						<div ng-if="forums[cForumID].description.length" class="description" ng-bind-html="forums[cForumID].description"></div>
					</div>
					<div class="td numThreads">{{forums[cForumID].totalThreadCount}}</div>
					<div class="td numPosts">{{forums[cForumID].totalPostCount}}</div>
					<div class="td lastPost">
						<span ng-if="forums[cForumID].latestPost.threadID">
							<a href="/user/{{forums[cForumID].latestPost.author.userID}}/" class="username" ng-bind-html="forums[cForumID].latestPost.author.username"></a><br><span class="datePosted">{{forums[cForumID].latestPost.datePosted}}</span>
						</span>
						<span ng-if="!forums[cForumID].latestPost.threadID">No Posts Yet!</span>
					</div>
				</div>
			</div>
		</div>
		<div class="tableDiv threadTable">
			<div ng-if="currentForum.permissions.createThread" hb-margined><a href="/forums/newThread/{{forumID}}/" class="fancyButton">New Thread</a></div>
			<div class="tr headerTR headerbar hbDark">
				<div class="td icon">&nbsp;</div>
				<div class="td threadInfo">Thread</div>
				<div class="td numPosts"># of Posts</div>
				<div class="td lastPost">Last Post</div>
			</div>
			<div class="sudoTable threadList hbdMargined">
				<div ng-repeat="thread in threads" class="tr">
					<div class="td icon"><div class="forumIcon" ng-class="{ 'sticky': thread.state.sticky, 'locked': thread.state.locked, 'newPosts': thread.newPosts }" title="{{thread.newPosts?'New':'No new'}} posts in thread" alt="{{thread.newPosts?'New':'No new'}} posts in thread"></div></div>
					<div class="td threadInfo">
						<a ng-if="thread.newPosts" href="/forums/thread/{{thread.threadID}}/?view=newPost#newPost"><img src="/images/forums/newPost.png" title="View new posts" alt="View new posts"></a>
						<div class="paginateDiv">
							<span ng-if="thread.postCount > PAGINATE_PER_PAGE">
								<span ng-if="getNumPages(thread.postCount) > 4">
									<a href="<?=$url?>?page=1">1</a>
									<div>...</div>
								</span>
								<a ng-repeat="page in paginateThread(thread.postCount)" href="/forums/thread/{{thread.threadID}}/?page={{page}}">{{page}}</a>
							</span>
							<a href="/forums/thread/{{thread.threadID}}/?view=lastPost#lastPost"><img src="/images/downArrow.png" title="Last post" alt="Last post"></a>
						</div>
						<a href="/forums/thread/{{thread.threadID}}/" ng-bind-html="thread.title"></a><br>
						<span class="threadAuthor">by <a href="/user/{{thread.author.userID}}/" class="username" ng-bind-html="thread.author.username"></a> on <span>{{thread.datePosted | amUtc | amLocal | amDateFormat: 'MMM D, YYYY h:mm a'}}</span></span>
					</div>
					<div class="td numPosts">{{thread.postCount}}</div>
					<div class="td lastPost">
						<a href="/user/{{thread.lastPost.author.userID}}/" class="username" ng-bind-html="thread.lastPost.author.username"></a><br><span>{{thread.lastPost.datePosted | amUtc | amLocal | amDateFormat: 'MMM D, YYYY h:mm a'}}</span>
					</div>
				</div>
				<div ng-if="threads.length == 0" class="tr noThreads">No threads yet</div>
			</div>
		</div>

		<div id="forumLinks" class="clearfix">
			<div id="forumOptions">
				<p ng-if="loggedIn"><a href="/forums/process/read/<?=$forumID?>/">Mark Forum As Read</a></p>
				<p ng-if="loggedIn"><a id="forumSub" href="/forums/process/subscribe/?forumID=<?=$forumID?>">{{subscribed?'Unsubscribe from':'Subscribe to'}} forum</a></p>
				<p ng-if="loggedIn"><a href="/forums/subscriptions/">Manage Subscriptions</a></p>
			</div>
			<paginate num-items="threads.length" items-per-page="PAGINATE_PER_PAGE" current="pagination.current" class="tr"></paginate>
		</div>
<?	require_once(FILEROOT.'/footer.php'); ?>