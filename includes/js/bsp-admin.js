jQuery(function() {
  /* Twitter */
  var $ = jQuery;
  var tweetPermalink = $('#bsp-tweet-permalink');
  if (tweetPermalink.length > 0) return;
  
  var tweetPost = {
    container:    $('#bsp-tweet-container'),
    successDiv:   $('#bsp-tweet-success'),
    successLink:  $('#bsp-tweet-success-link'),
    errorDiv:     $('#bsp-tweet-error'),
    errorMsg:     $('#bsp-tweet-error-message'),
    textarea:     $('#bsp-tweetbox'),
    charCount:    $('#bsp-char-count'),
    checkbox:     $('#bsp-tweet-this'),
    buttonDiv:    $('#bsp-tweet-button-div'),
    button:       $('#bsp-tweet-this-button'),
    spinner:      $('#bsp-tweet-this-spinner'),
    nonce:        $('#bsp-tweet-post-nonce').val(),
    postId:       null,
    setCharCount: function() {
      var remaining = 140 - this.textarea.val().length;
      if (this.textarea.val().search(/\[shortlink\]/i) != -1) {
        // A Bit.ly Shortlink is 21 characters
        // [shortlink] is 11 characters
        remaining -= 10;
      }
      this.charCount.text(remaining);
    }
  };
  tweetPost.postId = tweetPost.button.data('postid');
  tweetPost.setCharCount();
  
  tweetPost.checkbox.bind('click', function() {
    if ($(this).attr('checked') == 'checked') {
      tweetPost.buttonDiv.hide();
    }
    else {
      tweetPost.buttonDiv.show();
    }
  });
  
  tweetPost.textarea.bind('keyup change', function() {
    tweetPost.setCharCount();
  });
  
  tweetPost.button.bind('click', function(e) {
    e.preventDefault();
    if (tweetPost.textarea.val() === '') {
      alert('You must enter a tweet!');
      return;
    }
    tweetPost.spinner.css('visibility', 'visible');
    tweetPost.errorDiv.hide();
    tweetPost.successDiv.hide();
    var data = {
      action: 'bsp-tweet-post',
      bsp_nonce: tweetPost.nonce,
      post_id: tweetPost.postId,
      tweet_text: tweetPost.textarea.val()
    };
    $.post(ajaxurl, data, function(response) {
      if (response.status == 'success') {
        tweetPost.successLink.attr('href', response.data);
        tweetPost.successDiv.show();
        tweetPost.container.html('<a href="' + response.data + '" id="bsp-tweet-permalink">Tweet Permalink</a>');
      }
      else {
        tweetPost.spinner.css('visibility', 'hidden');
        tweetPost.errorMsg.text(response.data);
        tweetPost.errorDiv.show();
      }
    });
  });
});