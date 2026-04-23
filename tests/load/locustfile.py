from locust import HttpUser, task, between  # type: ignore

class RomarUser(HttpUser):
    wait_time = between(1, 3)

    @task
    def login(self):
        self.client.post("/auth/login.php", {
            "username": "admin",
            "password": "admin123",
            "csrf_token": "test"
        })

    @task(3)
    def dashboard(self):
        self.client.get("/modules/dashboard.php")

    @task(2)
    def assets(self):
        self.client.get("/modules/assets.php")

    @task
    def api_notifications(self):
        self.client.get("/api/getnotificationcount.php")
