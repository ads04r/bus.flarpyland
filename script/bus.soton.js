$(document).ready(function()
{

	if($('#update-timer').length > 0)
	{
		window.setInterval(function()
		{
			var url = window.location.href;
			$.get(url, function(data)
			{
				var content = $(data).find('#update-timer').html();
				$('#update-timer').html(content);
			});

		}, 30000);
	}

});
