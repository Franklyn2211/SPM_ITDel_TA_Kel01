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
						<div class="p-4" style="max-width: 370px; min-width: 320px; background: #fff; border-radius: 18px; box-shadow: 0 4px 32px 0 rgba(44,62,80,.08); margin: 0 auto;">
							<div class="text-center mb-4">
								<div class="d-inline-flex align-items-center justify-content-center mb-3 mt-2">
									<img src="../../../assets/img/logo.png" class="h-48px" alt="">
								</div>
								<h5 class="mb-0 fw-bold" style="letter-spacing:0.5px;">Sistem Penjaminan Mutu</h5>
							</div>

							{{-- ALERT ERROR LOGIN (modern style) --}}
							@if ($errors->any())
								<div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert" style="border-radius: 8px; font-size: 1rem;">
									<i class="ph-warning-circle me-2" style="font-size: 1.5rem;"></i>
									<div>
										@foreach ($errors->all() as $error)
											<div>{{ $error }}</div>
										@endforeach
									</div>
								</div>
							@endif

							<div class="mb-3">
								<label class="form-label fw-semibold">Username</label>
								<div class="form-control-feedback form-control-feedback-start">
									<input name="username" type="text" class="form-control py-2" placeholder="john@doe.com" value="{{ old('username') }}" autofocus required>
									<div class="form-control-feedback-icon">
										<i class="ph-user-circle text-muted"></i>
									</div>
								</div>
							</div>

							<div class="mb-3">
								<label class="form-label fw-semibold">Password</label>
								<div class="form-control-feedback form-control-feedback-start">
									<input name="password" type="password" class="form-control py-2" placeholder="•••••••••••" required>
									<div class="form-control-feedback-icon">
										<i class="ph-lock text-muted"></i>
									</div>
								</div>
							</div>

							<div class="mb-3">
								<button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" style="font-size:1.1rem;">Sign in</button>
							</div>
							{{-- <div class="text-center mt-2">
								<span class="form-text text-muted small">By continuing, you're confirming that you've read our <a href="#">Terms &amp; Conditions</a> and <a href="#">Cookie Policy</a></span>
							</div> --}}
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
