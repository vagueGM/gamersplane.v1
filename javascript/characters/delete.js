$(function () {
	$('form').append('<input type="hidden" name="modal" value="1">').ajaxForm({
		success: function (data) {
			if (data == 'deleted') {
				parent.$('#char_' + $('#characterID').val()).remove();
				parent.$.colorbox.close();
			}
		}
	});
});