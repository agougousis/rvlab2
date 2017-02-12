{!! Form::open(array('url'=>'rlogin', 'class'=>'form-signin')) !!}

    <div class="login-div">
        <img id="profile-img" class="profile-img-card" src="{{ asset('images/rvlab_image.png') }}" />
        <p id="profile-name" class="profile-name-card"></p>

        <input id="username" name="username" class="form-control" placeholder="Username">
        <input id="password" name="password" class="form-control" placeholder="Password">

        <button class="btn btn-lg btn-primary btn-block btn-signin" type="submit">Sign in</button>

        @if(session('loginMessage'))
            <div style="color: red; margin-top: 5px">Wrong username or password!</div>
        @endif
    </div>

{!! Form::close() !!}

<style type="text/css">
    .login-div {
        max-width: 400px;
        margin: 0 auto;
        text-align: center;
    }

    .login-div input {
        margin-bottom: 10px;
    }
</style>