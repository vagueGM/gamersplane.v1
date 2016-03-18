$(function() {
	$('.postAsChar .userAvatar').each(function () {
		var $img = $(this).find('img');
		$img.load(function () {
			console.log($img);
			$(this).parent().css({'top': '-' + ($img.height() / 2) + 'px', 'right': '-' + ($img.width() / 2) + 'px' });
		});
	});

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

controllers.controller('forum', ['$scope', 'Range', 'CurrentUser', 'ForumsService', function ($scope, Range, CurrentUser, ForumsService) {
	$scope.PAGINATE_PER_PAGE = PAGINATE_PER_PAGE;
	$scope.$emit('pageLoading');
	pathElements = getPathElements();
	$scope.threadID = pathElements[1]?parseInt(pathElements[1]):0;
	$scope.posts = [];
	$scope.pagination = { current: parseInt($.urlParam('page')) > 0?parseInt($.urlParam('page')):1 };
	CurrentUser.load().then(function (loggedIn) {
	});
});