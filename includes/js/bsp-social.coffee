# Compile from current dir: coffee -c .
$ = jQuery

userInfoFields =
  init: ->
    @name = $('#social-name-field')
    @profileImage = $('#social-image-field')
    @profileLink = $('#social-link-field')
  enable: ->
    @name.removeAttr 'disabled'
    @profileImage.removeAttr 'disabled'
    @profileLink.removeAttr 'disabled'
    this
  disable: ->
    @name.attr 'disabled', true
    @profileImage.attr 'disabled', true
    @profileLink.attr 'disabled', true
    this
  fill: (name, profileImage, profileLink) ->
    @name.val name
    @profileImage.val profileImage
    @profileLink.val profileLink
    this

class SocialConnect
  constructor: (service) ->
    $ =>
      @connectButton = $('#' + service + '-connect')
      @logoutButton = $('#' + service + '-logout')
      @userInfo = {
        container: $('#' + service + '-userinfo')
        name: null
        profileImage: {
          value: null
          tag: $('#' + service + '-profile-image') 
          fill: (@value) ->
            @tag.attr 'src', value
        }
        profileLink: {
          value: null
          tag: $('#' + service + '-profile-link')
          fill: (@value, name) ->
            @tag.attr 'href', value
            @tag.text name
        }
        fill: (@name, profileImage, profileLink) ->
          @profileImage.fill profileImage
          @profileLink.fill profileLink, @name
          this
      }
      @viaRadio = $('#comment-via-' + service)
      @viaRadio.click =>
        window.wpComments.hideCommenterInfo()
        userInfoFields
          .fill(@userInfo.name, @userInfo.profileImage.value, @userInfo.profileLink.value)
          .enable()
      
class FacebookConnect extends SocialConnect
  constructor: ->
    super 'facebook'
    window.fbAsyncInit = =>
      FB.init {
        appId : window.fbAppId  ## App ID
        status: true  ## check login status
        cookie: true  ## enable cookies to allow the server to access the session
        xfbml : true  ## parse XFBML
      }
      FB.Event.subscribe 'auth.statusChange', (response) =>
        if response.authResponse
          # user has auth'd your app and is logged into Facebook
          FB.api '/me', (me) =>
            @connectButton.hide()
            @userInfo
              .fill(me.name, "https://graph.facebook.com/#{me.id}/picture", me.link)
              .container.show()
        else
          # user has not auth'd your app, or is not logged into Facebook
          @connectButton.show()
          @userInfo.container.hide()
      @connectButton.bind 'click', (e) =>
        e.preventDefault()
        FB.login()
      @logoutButton.bind 'click', (e) =>
        e.preventDefault()
        FB.logout()
      
class TwitterConnect extends SocialConnect
  constructor: ->
    super 'twitter'
    twttr.anywhere (T) =>
      if T.isConnected()
        @connectButton.hide()
        @userInfo
          .fill(T.currentUser.data('name'), T.currentUser.data('profile_image_url'), "http://twitter.com/#{T.currentUser.data('screen_name')}")
          .container.show()
      T.bind 'authComplete', (e, user) =>
        @connectButton.hide()
        @userInfo
          .fill(user.name, user.profileImageUrl, "http://twitter.com/#{user.screenName}")
          .container.show()
      T.bind 'signOut', (e) =>
        @connectButton.show()
        @userInfo.container.hide()
      @connectButton.bind 'click', (e) =>
        e.preventDefault()
        T.signIn()
      @logoutButton.bind 'click', (e) =>
        e.preventDefault()
        twttr.anywhere.signOut()
    
fbConnect = new FacebookConnect()
twConnect = new TwitterConnect()

$ ->
  commentForm = window.wpComments.commentForm
  commentAuthor = window.wpComments.commentAuthor
  commentEmail = window.wpComments.commentEmail
  commentUrl = window.wpComments.commentUrl
  commentSubmit = window.wpComments.commentSubmit
  viaWpRadio = $('#comment-via-wordpress')
  userInfoFields.init()
  viaWpRadio.click ->
    window.wpComments.showCommenterInfo()
    userInfoFields.disable()
  commentSubmit.click (e) ->
    e.preventDefault()
    if !viaWpRadio.prop('checked')
      commentAuthor.input.val userInfoFields.name.val()
      commentEmail.input.val 'social@bubblessoc.net'
      commentUrl.input.val userInfoFields.profileLink.val()
    commentForm.submit()