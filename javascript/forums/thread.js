controllers.controller('thread', ['$scope', '$location', '$timeout', '$anchorScroll', 'Range', 'CurrentUser', 'ForumsService', function ($scope, $location, $timeout, $anchorScroll, Range, CurrentUser, ForumsService) {
	$scope.PAGINATE_PER_PAGE = PAGINATE_PER_PAGE;
	$scope.$emit('pageLoading');
	var pathElements = getPathElements();
	$scope.threadID = pathElements[2]?parseInt(pathElements[2]):0;
	$scope.posts = [];
	$scope.pagination = {
		numPosts: 1,
		itemsPerPage: PAGINATE_PER_PAGE,
		current: $.urlParam('page') && parseInt($.urlParam('page')) >= 1?parseInt($.urlParam('page')):1
	};
	var view = 'page';
	var viewVal = $scope.pagination.current;
	if ($.urlParam('p') && parseInt($.urlParam('p'))) {
		view = 'post';
		viewVal = parseInt($.urlParam('p'));
	} else if ($.urlParam('view') && $.urlParam('view') == 'newPost') {
		view = 'newPost';
	} else if ($.urlParam('view') && $.urlParam('view') == 'lastPost') {
		view = 'lastPost';
	}
	$scope.showAvatars = false;
	var postSide = 'l';
	$scope.cardVis = ['Visible', 'Hidden'];
	$scope.quickMod = {
		'combobox': { 'locked': 'Lock', 'sticky': 'Sticky', 'move': 'Move' },
		'action': null
	};
	CurrentUser.load().then(function (loggedIn) {
		$scope.loggedIn = loggedIn;
		if (loggedIn) {
			$scope.currentUser = CurrentUser.get();
			if (['r', 'l', 'c'].indexOf($scope.currentUser.usermeta.postSide) > -1) 
				postSide = $scope.currentUser.usermeta.postSide;
			$scope.thread = {};
			$scope.quickPost = {
				'postAs': null,
				'message': ''
			}
		}
		loadThread();
	});

	function loadThread() {
		ForumsService.getThread($scope.threadID, view, viewVal).then(function (data) {
			$scope.thread = data.thread;
			if ($scope.thread.locked) 
				$scope.quickMod.combobox.locked = 'Unlock';
			if ($scope.thread.sticky) 
				$scope.quickMod.combobox.sticky = 'Unsticky';
			if ($scope.thread.poll) {
				$scope.thread.poll.canVote = $scope.loggedIn && !$scope.thread.locked && (!$scope.thread.poll.voted || $scope.thread.poll.allowRevoting);
				$scope.thread.poll.votes = $scope.thread.poll.optionsPerUser == 1?null:[];
				$scope.thread.poll.options.forEach(function (option, index) {
					if ((!$scope.thread.poll.voted || $scope.thread.poll.allowRevoting) && option.voted) {
						if ($scope.thread.poll.optionsPerUser == 1) 
							$scope.thread.poll.votes = index;
						else 
							$scope.thread.poll.votes.push(index);
					}
					option.width = (50 + Math.floor(option.numVotes / $scope.thread.poll.highestVotes * 475)) + 'px';
					option.percentage = Math.floor(option.numVotes / $scope.thread.poll.totalVotes * 100);
				});
			}
			$scope.thread.posts.forEach(function (post, index) {
				post.postSide = postSide;
				if ($scope.loggedIn && $scope.currentUser.usermeta.postSide == 'c') 
					postSide = postSide == 'l'?'r':'l';
				post.permissions = {
					'edit': false,
					'delete': false
				};
				if ($scope.loggedIn && (post.author.userID == $scope.currentUser.userID && !$scope.thread.locked) || $scope.thread.permissions.moderate) {
					if ($scope.thread.permissions.editPost || $scope.thread.permissions.moderate) 
						post.permissions.edit = true;
					if ($scope.thread.permissions.moderate || ($scope.thread.permissions.deletePost && post.postID != $scope.thread.firstPostID) || ($scope.thread.permissions.deleteThread && post.postID == $scope.thread.firstPostID))
						post.permissions.delete = true;
				}
			});
			$scope.pagination.current = $scope.thread.page;
			$scope.pagination.numPosts = $scope.thread.postCount;
			$scope.characters = null;
			if ($scope.thread.characters) {
				$scope.characters = [{ 'value': 'player', 'display': 'Player' }];
				for (key in $scope.thread.characters)
					$scope.characters.push({ 'value': key, 'display': $scope.thread.characters[key] });
			}
			$scope.$emit('pageLoading');
			$timeout(function () {
				$scope.goToPost();
			});
		});
	};

	$scope.goToPost = function () {
		$anchorScroll();
	};

	$scope.toggleSubscribe = function () {
		ForumsService.toggleSub('t', $scope.threadID).then(function (data) {
			if (data.success) 
				$scope.thread.subscribed = $scope.thread.subscribed == 't'?null:'t';
		});
	};

	$scope.pollVote = function ($e) {
		$e.preventDefault();
	};

	$scope.toggleCardVis = function (postID, deckID, card) {
		if (Number.isInteger(postID) && Number.isInteger(deckID) && Number.isInteger(card.card)) {
			ForumsService.toggleCardVis(postID, deckID, card.card).then(function (data) {
				if (data.success) 
					card.visible = !card.visible;
			});
		}
	};

	$scope.changePage = function () {
		$scope.$emit('pageLoading');
		$location.hash('');
		view = 'page';
		viewVal = $scope.pagination.current;
		loadThread();
	};

	$scope.toggleThreadState = function(state) {
		if (state == 'locked' || state == 'sticky') {
			ForumsService.toggleThreadState($scope.threadID, state).then(function (data) {
				if (data.success) {
					stateVal = data[state];
					if (state == 'locked') {
						$scope.thread.locked = stateVal;
						$scope.quickMod.combobox.locked = $scope.quickMod.combobox.locked == 'Unlock'?'Lock':'Unlock';
					} else {
						$scope.thread.sticky = stateVal;
						$scope.quickMod.combobox.sticky = $scope.quickMod.combobox.sticky == 'Unsticky'?'Sticky':'Unsticky';
					}
				}
			});
		}
	};

	$scope.submitQuickMod = function () {
		var state = $scope.quickMod.action;
		if (state == 'locked' || state == 'sticky') 
			$scope.toggleThreadState(state);
		else 
			location.href = '/forums/moveThread/' + $scope.threadID + '/';
	};

	$scope.saveQuickPost = function ($event) {
		$event.preventDefault();
		$scope.$emit('pageLoading');
		ForumsService.savePost($.extend({ 'quickPost': true, 'threadID': $scope.threadID }, $scope.quickPost)).then(function (data) {
			if (data.success) {
				location.href = '/forums/thread/' + $scope.threadID + '/?p=' + data.postID + '##p' + data.postID;
/*				$location.hash('p' + data.postID);
				view = 'post';
				viewVal = data.postID;
				loadThread();*/
			} else 
				$scope.$emit('pageLoading');
		});
	};
}]).directive('roll', ['ToolsService', function (ToolsService) {
	return {
		restrict: 'E',
		template: '<div class="roll" ng-include="rollTemplate"></div>',
		scope: {
			'roll': '=',
			'showHidden': '='
		},
		link: function (scope, element, attrs) {
			scope.rollVisibilityText = ToolsService.rollVisibility;
			scope.rollTemplate = '/angular/templates/forums/rolls/' + scope.roll.type + '.html';

			scope.sweote = {
				'symbolText': {
					'success': 'Success',
					'advantage': 'Advantage',
					'triumph': 'Triumph',
					'failure': 'Failure',
					'threat': 'Threat',
					'despair': 'Despair',
					'whiteDot': 'White Force Point',
					'blackDot': 'Black Force Point'
				}
			}

			if (scope.roll.type == 'sweote') {
				scope.roll.total = [];
				if (scope.roll.counts.success != scope.roll.counts.failure) 
					scope.roll.total.push(Math.abs(scope.roll.counts.success - scope.roll.counts.failure) + ' ' + (scope.roll.counts.success > scope.roll.counts.failure?'Success':'Failure'));
				if (scope.roll.counts.advantage != scope.roll.counts.threat) 
					scope.roll.total.push(Math.abs(scope.roll.counts.advantage - scope.roll.counts.threat) + ' ' + (scope.roll.counts.advantage > scope.roll.counts.threat?'Advantage':'Threat'));
				if (scope.roll.counts.triumph) 
					scope.roll.total.push(scope.roll.counts.triumph + ' Triumph');
				if (scope.roll.counts.despair) 
					scope.roll.total.push(scope.roll.counts.despair + ' Despair');
				scope.roll.total = scope.roll.total.join(', ');
				counts = [];
				for (key in scope.roll.counts) 
					if (scope.roll.counts[key] != 0) 
						counts.push(scope.roll.counts[key] + ' ' + scope.sweote.symbolText[key]);
				scope.roll.counts = counts.join(', ');
			}
		}
	}
}]).filter('displayRolls', [function () {
	return function (rolls) {
		for (key in rolls) {
			if (Array.isArray(rolls[key])) 
				rolls[key] = '[ ' + rolls[key].join(', ') + ' ]';
		}
		return '( ' + rolls.join(', ') + ' )';
	}
}]);