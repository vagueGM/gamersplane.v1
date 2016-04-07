$(function() {
	$('#messageTextArea').markItUp(mySettings);

	$('.deletePost').colorbox();

	$('#forumSub').click(function (e) {
		e.preventDefault();

		$link = $(this);
		$.get($(this).attr('href'), {}, function (data) {
			if ($link.text().substring(0, 3) == 'Uns') 
				$link.text('Subscribe to ' + $link.text().split(' ')[2]);
			else 
				$link.text('Unsubscribe from ' + $link.text().split(' ')[2]);
		});
	});
});

controllers.controller('thread', ['$scope', 'Range', 'CurrentUser', 'ForumsService', function ($scope, Range, CurrentUser, ForumsService) {
	$scope.PAGINATE_PER_PAGE = PAGINATE_PER_PAGE;
	$scope.$emit('pageLoading');
	pathElements = getPathElements();
	$scope.threadID = pathElements[2]?parseInt(pathElements[2]):0;
	$scope.posts = [];
	$scope.pagination = { current: parseInt($.urlParam('page')) > 0?parseInt($.urlParam('page')):1 };
	$scope.showAvatars = false;
	$scope.postSide = 'l';
	$scope.cardVis = ['Visible', 'Hidden']
	CurrentUser.load().then(function (loggedIn) {
		$scope.loggedIn = loggedIn;
		$scope.currentUser = CurrentUser.get();
		if ($scope.currentUser.usermeta.postSide == 'r') 
			$scope.postSide = 'r';
		$scope.thread = {};
		ForumsService.getThread($scope.threadID, $scope.pagination.current).then(function (data) {
			$scope.thread = data.thread;
			if ($scope.thread.poll) {
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
			$scope.$emit('pageLoading');
		});
	});

	$scope.toggleCardVis = function (postID, deckID, card) {
		if (Number.isInteger(postID) && Number.isInteger(deckID) && Number.isInteger(card.card)) {
			ForumsService.toggleCardVis(postID, deckID, card.card).then(function (data) {
				if (data.success) 
					card.visible = !card.visible;
			});
		}
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