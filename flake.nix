{
  description = "DutyManager dev shell - PHP 8.4 + Composer matching the Docker image, for a native test loop without rebuilding containers.";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-24.11";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = nixpkgs.legacyPackages.${system};

        # Same PHP version + extensions as the Dockerfile's test/runtime stages
        # (curl, mbstring, intl) - keep these two lists in sync.
        php = pkgs.php84.withExtensions ({ enabled, all }: enabled ++ [
          all.curl
          all.mbstring
          all.intl
        ]);
      in
      {
        devShells.default = pkgs.mkShell {
          packages = [
            php
            php.packages.composer
            pkgs.git
          ];

          shellHook = ''
            echo "PHP $(php -r 'echo PHP_VERSION;') ready - run 'composer install' once, then 'vendor/bin/tester tests/'"
            # Only re-launch into zsh for interactive sessions (not nix develop -c "...").
            # $SHELL can't be trusted here - nix develop overwrites it to its own bash before running this hook.
            [[ $- == *i* ]] && exec zsh -i
          '';
        };
      });
}
