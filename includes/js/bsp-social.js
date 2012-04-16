(function() {
  var $, FacebookConnect, SocialConnect, TwitterConnect, fbConnect, twConnect, userInfoFields;
  var __hasProp = Object.prototype.hasOwnProperty, __extends = function(child, parent) { for (var key in parent) { if (__hasProp.call(parent, key)) child[key] = parent[key]; } function ctor() { this.constructor = child; } ctor.prototype = parent.prototype; child.prototype = new ctor; child.__super__ = parent.prototype; return child; };

  $ = jQuery;

  userInfoFields = {
    init: function() {
      this.name = $('#social-name-field');
      this.profileImage = $('#social-image-field');
      return this.profileLink = $('#social-link-field');
    },
    enable: function() {
      this.name.removeAttr('disabled');
      this.profileImage.removeAttr('disabled');
      this.profileLink.removeAttr('disabled');
      return this;
    },
    disable: function() {
      this.name.attr('disabled', true);
      this.profileImage.attr('disabled', true);
      this.profileLink.attr('disabled', true);
      return this;
    },
    fill: function(name, profileImage, profileLink) {
      this.name.val(name);
      this.profileImage.val(profileImage);
      this.profileLink.val(profileLink);
      return this;
    }
  };

  SocialConnect = (function() {

    function SocialConnect(service) {
      var _this = this;
      $(function() {
        _this.connectButton = $('#' + service + '-connect');
        _this.logoutButton = $('#' + service + '-logout');
        _this.userInfo = {
          container: $('#' + service + '-userinfo'),
          name: null,
          profileImage: {
            value: null,
            tag: $('#' + service + '-profile-image'),
            fill: function(value) {
              this.value = value;
              return this.tag.attr('src', value);
            }
          },
          profileLink: {
            value: null,
            tag: $('#' + service + '-profile-link'),
            fill: function(value, name) {
              this.value = value;
              this.tag.attr('href', value);
              return this.tag.text(name);
            }
          },
          fill: function(name, profileImage, profileLink) {
            this.name = name;
            this.profileImage.fill(profileImage);
            this.profileLink.fill(profileLink, this.name);
            return this;
          }
        };
        _this.viaRadio = $('#comment-via-' + service);
        return _this.viaRadio.click(function() {
          window.wpComments.hideCommenterInfo();
          return userInfoFields.fill(_this.userInfo.name, _this.userInfo.profileImage.value, _this.userInfo.profileLink.value).enable();
        });
      });
    }

    return SocialConnect;

  })();

  FacebookConnect = (function() {

    __extends(FacebookConnect, SocialConnect);

    function FacebookConnect() {
      var _this = this;
      FacebookConnect.__super__.constructor.call(this, 'facebook');
      window.fbAsyncInit = function() {
        FB.init({
          appId: window.fbAppId,
          status: true,
          cookie: true,
          xfbml: true
        });
        FB.Event.subscribe('auth.statusChange', function(response) {
          if (response.authResponse) {
            return FB.api('/me', function(me) {
              _this.connectButton.hide();
              return _this.userInfo.fill(me.name, "https://graph.facebook.com/" + me.id + "/picture", me.link).container.show();
            });
          } else {
            _this.connectButton.show();
            return _this.userInfo.container.hide();
          }
        });
        _this.connectButton.bind('click', function(e) {
          e.preventDefault();
          return FB.login();
        });
        return _this.logoutButton.bind('click', function(e) {
          e.preventDefault();
          return FB.logout();
        });
      };
    }

    return FacebookConnect;

  })();

  TwitterConnect = (function() {

    __extends(TwitterConnect, SocialConnect);

    function TwitterConnect() {
      var _this = this;
      TwitterConnect.__super__.constructor.call(this, 'twitter');
      twttr.anywhere(function(T) {
        if (T.isConnected()) {
          _this.connectButton.hide();
          _this.userInfo.fill(T.currentUser.data('name'), T.currentUser.data('profile_image_url'), "http://twitter.com/" + (T.currentUser.data('screen_name'))).container.show();
        }
        T.bind('authComplete', function(e, user) {
          _this.connectButton.hide();
          return _this.userInfo.fill(user.name, user.profileImageUrl, "http://twitter.com/" + user.screenName).container.show();
        });
        T.bind('signOut', function(e) {
          _this.connectButton.show();
          return _this.userInfo.container.hide();
        });
        _this.connectButton.bind('click', function(e) {
          e.preventDefault();
          return T.signIn();
        });
        return _this.logoutButton.bind('click', function(e) {
          e.preventDefault();
          return twttr.anywhere.signOut();
        });
      });
    }

    return TwitterConnect;

  })();

  fbConnect = new FacebookConnect();

  twConnect = new TwitterConnect();

  $(function() {
    var commentAuthor, commentEmail, commentForm, commentSubmit, commentUrl, viaWpRadio;
    commentForm = window.wpComments.commentForm;
    commentAuthor = window.wpComments.commentAuthor;
    commentEmail = window.wpComments.commentEmail;
    commentUrl = window.wpComments.commentUrl;
    commentSubmit = window.wpComments.commentSubmit;
    viaWpRadio = $('#comment-via-wordpress');
    userInfoFields.init();
    viaWpRadio.click(function() {
      window.wpComments.showCommenterInfo();
      return userInfoFields.disable();
    });
    return commentSubmit.click(function(e) {
      e.preventDefault();
      if (!viaWpRadio.prop('checked')) {
        commentAuthor.input.val(userInfoFields.name.val());
        commentEmail.input.val('social@bubblessoc.net');
        commentUrl.input.val(userInfoFields.profileLink.val());
      }
      return commentForm.submit();
    });
  });

}).call(this);
