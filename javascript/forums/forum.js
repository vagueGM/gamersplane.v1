$(function () {
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

/*controllers.controller('forum', ['$scope', 'CurrentUser', 'ForumsService', function ($scope, CurrentUser, ForumsService) {
	$scope.$emit('pageLoading');
	pathElements = getPathElements();
	CurrentUser.load().then(function () {
		$scope.$emit('pageLoading');
		ForumsService.getForum(pathElements[1]);
	});
}]);*/