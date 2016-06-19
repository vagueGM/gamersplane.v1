controllers.controller('post', ['$scope', '$location', '$timeout', '$cookies', 'CurrentUser', 'ForumsService', function ($scope, $location, $timeout, $cookies, CurrentUser, ForumsService) {
	$scope.$emit('pageLoading');
	var pathElements = getPathElements();
	$scope.pageType = pathElements[1];
	$scope.header = '';
	$scope.post = {
		title: '',
		postAs: null,
		message: '',
		rolls: [],
		draws: {}
	};
	$scope.deleteRoll = null;
	$scope.thread = {
		threadID: 0,
		title: '',
		permissions: {},
		forumHeritage: [],
		options: {
			sticky: false,
			locked: false,
			allowRolls: false,
			allowDraws: false
		},
		poll: {
			delete: false,
			question: '',
			options: '',
			optionsPerUser: 1,
			allowRevoting: false
		}
	};
	$scope.decks = [];
	$scope.combobox = {
		characters: [{ value: 'p', display: 'Player' }],
		diceTypes: [
			{ value: 'basic', display: 'Basic' },
			{ value: 'sweote', display: 'SWEOTE' },
			{ value: 'Fate', display: 'Fate' },
			{ value: 'fengshui', display: 'Feng Shui' }
		],
		values: {
			addDice: 'basic'
		}
	};
	$scope.firstPost = false;
	$scope.options = {
		state: 'rolls_decks',
		title: 'Rolls and Decks'
	};
	$scope.optionsStates = {
		'options': 'Options',
		'poll': 'Poll',
		'rolls_decks': 'Rolls and Decks'
	};
	CurrentUser.load().then(function (loggedIn) {
		$scope.loggedIn = loggedIn;
		if (loggedIn)
			$scope.currentUser = CurrentUser.get();
		if ($scope.pageType == 'post') {
			$scope.postID = pathElements[2]?parseInt(pathElements[2]):0;
			ForumsService.getThreadBasic($scope.postID).then(function (data) {
				if (data.success) {
					// $scope.thread = data.thread;
					$scope.thread.threadID = data.thread.threadID;
					$scope.thread.title = data.thread.title;
					$scope.thread.permissions = data.thread.permissions;
					$scope.thread.forumHeritage = data.thread.forumHeritage;
					$scope.thread.options.allowRolls = data.thread.allowRolls;
					$scope.thread.options.allowDraws = data.thread.allowDraws;
					$scope.decks = data.decks;
					console.log($scope.decks);
					if ($scope.thread.locked || !$scope.thread.permissions.write)
						location.href = '/forums/';
					$scope.header = 'Post a reply - ' + $scope.thread.title;
					for (var charID in $scope.thread.characters)
						$scope.combobox.characters.push({
							value: charID,
							display: $scope.thread.characters[charID]
						});
					advancedPost = $cookies.getObject('advancedPost');
					$scope.post.title = 'Re: ' + $scope.thread.title;
					if (advancedPost) {
						$timeout(function () { $scope.post.postAs = advancedPost.postAs; });
						$scope.post.message = advancedPost.message;
						// $cookies.remove('advancedPost', { path: '/forums/', domain: '.gamersplane.local' });
					}
					if (pathElements[1] == 'editPost' && $scope.postID == $scope.thread.firstPostID) {
						$scope.firstPost = true;
					}

					$scope.$emit('pageLoading');
				} else
					window.location.href = '/forums/';
			});
		} else if ($scope.pageType == 'newThread') {
			$scope.firstPost = true;
			$scope.forumID = pathElements[2]?parseInt(pathElements[2]):0;
			ForumsService.getForum($scope.forumID, null, null, ['title', 'permissions', 'heritage']).then(function (data) {
				if (data.success && data.forums[$scope.forumID].permissions.createThread) {
					$scope.thread.permissions = data.forums[$scope.forumID].permissions;
					$scope.thread.forumHeritage = data.forums[$scope.forumID].heritage;
					$scope.setOptionsState('options');
					if ($scope.thread.permissions.addRolls)
						$scope.thread.options.allowRolls = true;
					if ($scope.thread.permissions.addDraws)
						$scope.thread.options.allowDraws = true;
					$scope.decks = data.decks;
					$scope.header = 'New thread - ' + data.forums[$scope.forumID].title;
				} //else
					// window.location.href = '/forums/';
			});
			$scope.$emit('pageLoading');
		}
	});
	$scope.setOptionsState = function (state) {
		if (state in $scope.optionsStates) {
			$scope.options.state = state;
			$scope.options.title = $scope.optionsStates[state];
		}
	};
	$scope.addRoll = function () {
		$scope.post.rolls.push({ type: $scope.combobox.values.addDice });
	};
	var previewLock = false;
	$scope.save = function () {
		if (previewLock) {
			previewLock = false;
			return;
		}
		if ($scope.post.title.length === 0 || $scope.post.message.length === 0) {
			return;
		}

		var postData = {};
		postData.type = $scope.pageType;
		if (postData.type == 'newThread') {
			postData.forumID = $scope.forumID;
		}
		$.extend(postData, $scope.post);
		if ($scope.firstPost) {
			postData.options = $scope.thread.options;
			postData.poll = $scope.thread.poll;
		}
		ForumsService.savePost(postData).then(function (data) {
		});
	};
	$scope.preview = function () {
		previewLock = true;
		console.log('preview');
	};
}]).directive('newRoll', ['$timeout', function ($timeout) {
	return {
		restrict: 'E',
		scope: {
			'data': '='
		},
		template: '<div ng-if="visible" class="rollWrapper"><button ng-click="removeRoll()" class="sprite cross small"></button><div class="newRoll" ng-class="type + \'Roll\'"><ng-include src="\'/angular/templates/forums/rolls/post/\' + type + \'.html\'"></ng-include></div></div>',
		link: function (scope, element, attrs) {
			scope.visible = true;
			scope.type = scope.data.type;
			scope.combobox = {
				visibility: {
					0: 'Hide Nothing',
					1: 'Hide Roll/Result',
					2: 'Hide Dice &amp; Roll',
					3: 'Hide Everything'
				}
			};
			holdData = scope.data;
			scope.data = {
				reason: '',
				visibility: 0
			};
			scope.removeRoll = function () {
				scope.data = null;
				scope.visible = false;
			};
			if (scope.type == 'basic') {
				scope.data.rolls = '';
				scope.data.rerollAces = false;
			} else if (scope.type == 'sweote') {
				scope.diceTypes = {
					'a': 'ability',
					'p': 'proficiency',
					'b': 'boost',
					'd': 'difficulty',
					'c': 'challenge',
					's': 'setback',
					'f': 'force'
				};
				scope.data.rolls = [];

				var hideDiceOptions = function ($event) {
					if (!($($event.target).is('.dice') || $($event.target).is('.diceIcon')))
						$('div.diceOptions').hide();
				};
				var hideDiceOptions_bound = false;
				$._data( $('body')[0] ).events.click.forEach(function (event, index) {
					if (event.handler == hideDiceOptions)
						hideDiceOptions_bound = true;
				});
				if (!hideDiceOptions_bound)
					$('body').click(hideDiceOptions);

				element.on('click', '.add', function (e) {
					e.stopPropagation();

					$(this).siblings('.diceOptions').toggle();
				});
				scope.addDie = function (dieType) {
					if (dieType in scope.diceTypes)
						scope.data.rolls.push(dieType);
				};
				scope.removeDie = function (index) {
					scope.data.rolls.splice(index, 1);
				};
			} else if (scope.type == 'fate') {
				scope.data.rolls = 4;
			} else if (scope.type == 'fengshui') {
				scope.data.actionValue = 0;
				scope.data.modifier = 'Standard';
				scope.combobox.modifierTypes = ['Standard', 'Fortune', 'Closed'];
			}
		}
	};
}]);
