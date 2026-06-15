import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { describe, it, expect, vi, afterEach } from "vitest";
import LoginPage from "../LoginPage";
import * as client from "../../api/client";

const mockNavigate = vi.fn();
vi.mock("react-router-dom", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-router-dom")>();
  return { ...actual, useNavigate: () => mockNavigate };
});

function renderLoginPage() {
  return render(
    <MemoryRouter>
      <LoginPage />
    </MemoryRouter>,
  );
}

afterEach(() => {
  vi.restoreAllMocks();
  mockNavigate.mockReset();
});

describe("LoginPage", () => {
  describe("rendering", () => {
    it("should render email field, password field and submit button", () => {
      renderLoginPage();

      expect(screen.getByLabelText("Email")).toBeInTheDocument();
      expect(screen.getByLabelText("Password")).toBeInTheDocument();
      expect(screen.getByRole("button", { name: "Sign in" })).toBeInTheDocument();
    });
  });

  describe("required field validation", () => {
    it("should show email required error when submitting with empty email", async () => {
      renderLoginPage();

      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      expect(screen.getByText("Email is required")).toBeInTheDocument();
    });

    it("should show password required error when submitting with empty password", async () => {
      renderLoginPage();
      await userEvent.type(screen.getByLabelText("Email"), "user@example.com");

      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      expect(screen.getByText("Password is required")).toBeInTheDocument();
    });

    it("should block submission when required fields are empty", async () => {
      const postSpy = vi.spyOn(client.api, "post");
      renderLoginPage();

      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      expect(postSpy).not.toHaveBeenCalled();
    });
  });

  describe("email format validation", () => {
    it("should show format error on submit with malformed email", async () => {
      renderLoginPage();
      await userEvent.type(screen.getByLabelText("Email"), "not-an-email");
      await userEvent.type(screen.getByLabelText("Password"), "secret");

      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      expect(screen.getByText("Enter a valid email address")).toBeInTheDocument();
    });

    it("should show format error on blur with invalid email", async () => {
      renderLoginPage();
      const emailInput = screen.getByLabelText("Email");

      await userEvent.type(emailInput, "bad-email");
      fireEvent.blur(emailInput);

      expect(screen.getByText("Enter a valid email address")).toBeInTheDocument();
    });

    it("should clear email error on blur when email becomes valid", async () => {
      renderLoginPage();
      const emailInput = screen.getByLabelText("Email");

      // First make it invalid
      await userEvent.type(emailInput, "bad");
      fireEvent.blur(emailInput);
      expect(screen.getByText("Enter a valid email address")).toBeInTheDocument();

      // Fix it and blur again
      await userEvent.clear(emailInput);
      await userEvent.type(emailInput, "good@example.com");
      fireEvent.blur(emailInput);

      expect(screen.queryByText("Enter a valid email address")).not.toBeInTheDocument();
    });
  });

  describe("successful login", () => {
    it("should call the auth API with email and password on valid submit", async () => {
      const postSpy = vi.spyOn(client.api, "post").mockResolvedValue({ token: "tok" });
      renderLoginPage();

      await userEvent.type(screen.getByLabelText("Email"), "user@example.com");
      await userEvent.type(screen.getByLabelText("Password"), "password123");
      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      expect(postSpy).toHaveBeenCalledWith("/auth/login", {
        email: "user@example.com",
        password: "password123",
      });
    });

    it("should navigate to / after successful login", async () => {
      vi.spyOn(client.api, "post").mockResolvedValue({ token: "tok" });
      renderLoginPage();

      await userEvent.type(screen.getByLabelText("Email"), "user@example.com");
      await userEvent.type(screen.getByLabelText("Password"), "password123");
      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      await waitFor(() => expect(mockNavigate).toHaveBeenCalledWith("/"));
    });
  });

  describe("failed login", () => {
    it("should display API error message when login fails", async () => {
      vi.spyOn(client.api, "post").mockRejectedValue(new Error("Invalid credentials"));
      renderLoginPage();

      await userEvent.type(screen.getByLabelText("Email"), "user@example.com");
      await userEvent.type(screen.getByLabelText("Password"), "wrongpass");
      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      await waitFor(() =>
        expect(screen.getByRole("alert", { name: /invalid credentials/i })).toBeInTheDocument(),
      );
    });

    it("should show fallback message for non-Error rejections", async () => {
      vi.spyOn(client.api, "post").mockRejectedValue("network failure");
      renderLoginPage();

      await userEvent.type(screen.getByLabelText("Email"), "user@example.com");
      await userEvent.type(screen.getByLabelText("Password"), "pass");
      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      await waitFor(() =>
        expect(screen.getByText("Login failed. Please try again.")).toBeInTheDocument(),
      );
    });
  });

  describe("loading state", () => {
    it("should disable button and show loading text while submitting", async () => {
      let resolve!: () => void;
      vi.spyOn(client.api, "post").mockReturnValue(
        new Promise((res) => { resolve = () => res({ token: "t" }); }),
      );
      renderLoginPage();

      await userEvent.type(screen.getByLabelText("Email"), "user@example.com");
      await userEvent.type(screen.getByLabelText("Password"), "pass");
      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      const btn = screen.getByRole("button");
      expect(btn).toBeDisabled();
      expect(btn).toHaveTextContent("Signing in\u2026");

      resolve();
      await waitFor(() => expect(btn).not.toBeDisabled());
    });

    it("should re-enable button after failed submission", async () => {
      vi.spyOn(client.api, "post").mockRejectedValue(new Error("bad"));
      renderLoginPage();

      await userEvent.type(screen.getByLabelText("Email"), "user@example.com");
      await userEvent.type(screen.getByLabelText("Password"), "pass");
      await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

      await waitFor(() =>
        expect(screen.getByRole("button", { name: "Sign in" })).not.toBeDisabled(),
      );
    });
  });
});
