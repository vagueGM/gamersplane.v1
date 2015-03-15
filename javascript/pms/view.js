controllers.controller('pmView', function ($scope, $cookies, $http, DeletePM) {
	pathElements = getPathElements();

	$scope.allowDelete = true;
	$http.post('http://api.gamersplane.local/pms/view/', { pmID: pathElements[2], markRead: true }).success(function (data) {
		if (data.failed || data.noPM) 
			document.location = '/pms/';

		data.datestamp = convertTZ(data.datestamp, 'YYYY-MM-DD HH:mm:ss', 'MMMM D, YYYY h:mm a');
		for (key in data) 
			$scope[key] = data[key];

		replyTo = parseInt(data.replyTo);
		if (!isNaN(replyTo)) {
			$scope.history = new Array();
			$scope.hasHistory = true;
			$http.post('http://api.gamersplane.local/pms/view/', { pmID: replyTo }).success(function (historyPM) {
				historyPM.datestamp = convertTZ(historyPM.datestamp, 'YYYY-MM-DD HH:mm:ss', 'MMMM D, YYYY h:mm a');
				replyTo = historyPM.replyTo;
				$scope.history.push(historyPM);
			});
		}
	});

	$scope.delete = function () {
		DeletePM(pathElements[2]).success(function (data) {
			if (!isNaN(data.deleted)) 
				document.location = '/pms/';
		});
	}
});
