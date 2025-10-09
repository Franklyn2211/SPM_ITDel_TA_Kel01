<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>Login - Sistem Penjaminan Mutu</title>
    <link href="assets/img/logo.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

	<!-- Global stylesheets -->
	<link href="../../../assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
	<link href="../../../assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
	<link href="assets/css/ltr/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">
	<!-- /global stylesheets -->

	<!-- Core JS files -->
	<script src="../../../assets/demo/demo_configurator.js"></script>
	<script src="../../../assets/js/bootstrap/bootstrap.bundle.min.js"></script>
	<!-- /core JS files -->

	<!-- Theme JS files -->
	<script src="assets/js/app.js"></script>
	<!-- /theme JS files -->

</head>

<body>

	<!-- Page content -->
	<div class="page-content">

		<!-- Main content -->
		<div class="content-wrapper">

			<!-- Inner content -->
			<div class="content-inner">

				<!-- Content area -->
				<div class="content d-flex justify-content-center align-items-center">

					<!-- Login card -->
					<form method="POST" class="login-form" action="{{route('login.do')}}">
                        @csrf
						<div class="p-3">
							<div class="text-center mb-3">
								<div class="d-inline-flex align-items-center justify-content-center mb-4 mt-2">
									<img src="../../../assets/img/logo.png" class="h-48px" alt="">
								</div>
								<h5 class="mb-0">Sistem Penjaminan Mutu</h5>
							</div>

							<div class="mb-3">
								<label class="form-label">Username</label>
								<div class="form-control-feedback form-control-feedback-start">
									<input name="username" type="text" class="form-control" placeholder="john@doe.com">
									<div class="form-control-feedback-icon">
										<i class="ph-user-circle text-muted"></i>
									</div>
								</div>
							</div>

							<div class="mb-3">
								<label class="form-label">Password</label>
								<div class="form-control-feedback form-control-feedback-start">
									<input name="password" type="password" class="form-control" placeholder="•••••••••••">
									<div class="form-control-feedback-icon">
										<i class="ph-lock text-muted"></i>
									</div>
								</div>
							</div>

							<div class="d-flex align-items-center mb-3">
								<label class="form-check">
									<input type="checkbox" name="remember" class="form-check-input" checked>
									<span class="form-check-label">Remember</span>
								</label>

								<a href="login_password_recover.html" class="ms-auto">Forgot password?</a>
							</div>

							<div class="mb-3">
								<button type="submit" class="btn btn-primary w-100">Sign in</button>
							</div>
							<span class="form-text text-center text-muted">By continuing, you're confirming that you've read our <a href="#">Terms &amp; Conditions</a> and <a href="#">Cookie Policy</a></span>
						</div>
					</form>
					<!-- /login card -->

				</div>
				<!-- /content area -->

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->
</body>
</html>
